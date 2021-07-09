<?php
namespace Alr\Scrappy\Scrappers;

use Alr\Scrappy\Agent;
use App\Models\Proxy;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

class ProxyCurlScrapper implements ScrapperInterface
{
    const CONNECTION_TIMEOUT = 5;
    const MAX_ATTEMPS = 500;

    private $proxy;
    /** @var Collection */
    private $proxies;
    private $invalid_regex = [];

    public function declareInvalidByRegex($invalid_regex = [])
    {
        $this->invalid_regex = $invalid_regex;
    }

    /**
     * @param $url
     * @return mixed
     */
    public function get($url, $options = [])
    {
        $proxy_list = new \Alr\Scrappy\ProxyList();

        Log::debug('Downloading URL: '.\Str::limit($url, 80, '[...]'));

        $attempts = 0;
        do {
            try {
                $proxy = $proxy_list->selectNextProxy();
                Log::debug('Using proxy: ' . $proxy->ip . ':' . $proxy->port . ' last time used ' . $proxy->proxy_load_time . 's');
                $init = microtime(true);
                try {
                    list($curl_scraped_page, $error) = $this->makeProxyRequest($url, $proxy, $options);

                    foreach ($this->invalid_regex as $regex) {
                        if (preg_match($regex, $curl_scraped_page)) {
                            throw new \Exception();
                        }
                    }
                } catch (\Throwable $e) {
                    $curl_scraped_page = null;
                }
                $end = microtime(true);
                $time = $end - $init;

                if (!$curl_scraped_page) {
                    $attempts++;
                    $proxy->proxy_load_time += $time;
                    Log::error('Download attempt with ('.$proxy->ip . ':' . $proxy->port.')['.$proxy->proxy_load_time.'s] '.str_limit($url, 80, '[...]').' '.$error);
                } else {
                    $proxy->proxy_load_time = $time;
                }
                $proxy->save();
            } catch (\Exception $e) {
                Log::error('Error in proxy loop, just jumping...');
            }
        } while($curl_scraped_page == false && $attempts <= self::MAX_ATTEMPS);
        $proxy_list->markUsedProxy();
        Log::info('Download completed ('.$proxy->ip . ':' . $proxy->port.')['.$proxy->proxy_load_time.'s] '.\Str::limit($url, 80, '[...]'));
        return $curl_scraped_page;
    }

    /**
     * @param $url
     * @return mixed
     */
    public function makeProxyRequest($url, $proxy, $options = [])
    {
        $ch = curl_init();

        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING       => 'UTF-8',
            CURLOPT_AUTOREFERER    => true,
            CURLOPT_CONNECTTIMEOUT => self::CONNECTION_TIMEOUT,
            CURLOPT_TIMEOUT        => self::CONNECTION_TIMEOUT,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_USERAGENT      => Agent::randomUserAgent(),
            CURLOPT_HTTP_CONTENT_DECODING => false,
            CURLOPT_SSL_VERIFYPEER => false,
        ];

        $options[CURLOPT_PROXY] = $proxy->ip . ':' . $proxy->port;
//        $options[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;
        if(isset($proxy->user) && $proxy->user) {
            $options[CURLOPT_PROXYUSERPWD] = $proxy->user . ':' . $proxy->password;
        }

        $this->getOptions($ch, $options);

        $curl_scraped_page = curl_exec($ch);

        $info = curl_getinfo($ch);
        $error = null;

        if ($info['http_code'] != 200) {
            $curl_scraped_page = false;
            $error = 'HTTP CODE is not 200';
        }

        if($curl_scraped_page === false) {
            $error = curl_error($ch);
        }

        curl_close($ch);
        return [$curl_scraped_page, $error];
    }

    private function getOptions($ch, $options)
    {
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);

        foreach ($options as $key => $value) {
            curl_setopt($ch, $key, $value);
        }
    }
}