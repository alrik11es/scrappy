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
    const PROXY_DELAY = 10;

    private $proxy;
    /** @var Collection */
    private $proxies;

    /**
     * @param $url
     * @return mixed
     */
    public function get($url, $options = [])
    {
        $proxy = new \Alr\Scrappy\ProxyList();
        $this->proxies = $proxy->proxies;

        Log::debug('Downloading URL: '.\Str::limit($url, 80, '[...]'));

        $attempts = 0;
        do {
            try {
                $this->getProxyMinTime();
                Log::debug('Using proxy: ' . $this->proxy->ip . ':' . $this->proxy->port . ' last time used ' . $this->proxy->proxy_load_time . 's');
                $init = microtime(true);
                try {
                    $curl_scraped_page = $this->makeRequest($url, $this->proxy, $options);
                } catch (\Throwable $e) {
                    $curl_scraped_page = null;
                }
                $end = microtime(true);
                $time = $end - $init;
                if (!$curl_scraped_page) {
                    $attempts++;
                    $this->proxy->proxy_load_time += $time;
                    Log::debug('Oops. This proxy has failed... trying again!');
                } else {
                    $this->proxy->proxy_load_time = $time;
                }
                $proxy->saveList();
            } catch (\Exception $e) {
                Log::error('Error in proxy loop, just jumping...');
            }
        } while($curl_scraped_page == false && $attempts <= self::MAX_ATTEMPS);
        Log::debug('Download done: '.\Str::limit($url, 80, '[...]'));
        return $curl_scraped_page;
    }

    /**
     * @param $url
     * @param Proxy $proxy
     * @return mixed
     */
    private function makeRequest($url, $proxy, $options)
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
        if(isset($proxy->user) && $proxy->user) {
            $options[CURLOPT_PROXYUSERPWD] = $proxy->user . ':' . $proxy->password;
        }

        $this->getOptions($ch, $options);

        $curl_scraped_page = curl_exec($ch);

        if($curl_scraped_page === false)
        {
            $error = curl_error($ch);
            Log::error('Attempt to download with ('.$proxy->proxy_load_time.') ('.$proxy->ip . ':' . $proxy->port.') '.str_limit($url, 80, '[...]').' failed ('.$error.')');
        }

        $info = curl_getinfo($ch);

        curl_close($ch);
        return $curl_scraped_page;
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

    private function getProxyMinTime()
    {
        $this->proxies = $this->proxies->where('updated_at', '<=', Carbon::now()->subSeconds(self::PROXY_DELAY)->toDateTimeString())->sortBy('proxy_load_time');
        $this->proxy = $this->proxies->first();
    }
}