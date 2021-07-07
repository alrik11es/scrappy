<?php


namespace Alr\Scrappy;

use Alr\Scrappy\Scrappers\ProxyCurlScrapper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ProxyList
{
    public $proxies;
    private $selected_proxy;

    public function __construct()
    {
        $this->loadProxies();
    }

    public function loadProxies()
    {
        $this->proxies = \Cache::get('scrappy_proxies');

//        if (empty($this->proxies)) {
//            $this->proxies = collect([]);
//            $this->updateProxyList();
//        } else {
        $this->proxies = collect(json_decode($this->proxies));
//        }
    }

    public function getProxy($proxy)
    {
        $this->loadProxies();
        return $this->proxies
            ->where('ip', '=', $proxy->ip)
            ->where('port', '=', $proxy->port)
            ->first();
    }

    public function markUsedProxy()
    {
        \Cache::forever('scrappy_used_proxy', implode(':', [$this->selected_proxy->ip, $this->selected_proxy->port]));
    }

    public function selectNextProxy()
    {
        $this->loadProxies();
//        $this->deleteBadPerformingProxies();
//        if (count($this->proxies) == 0) {
//            $this->updateProxyList();
//        }

        $scrappy_used_proxy = \Cache::get('scrappy_used_proxy');
        if($scrappy_used_proxy) {
            list($ip, $port) = explode(':', $scrappy_used_proxy);
            $valid_proxies = clone $this->proxies
                ->where('ip', '!=', $ip)
                ->where('port', '!=', $port)
                ->sortBy('proxy_load_time');
        } else {
            $valid_proxies = clone $this->proxies->sortBy('proxy_load_time');
        }
        $selected_proxy = $valid_proxies->keys()->first();
        $this->selected_proxy = $this->proxies[$selected_proxy];
        return $this->proxies[$selected_proxy];
    }

    public function saveProxyList()
    {
        \Cache::forever('scrappy_proxies', json_encode($this->proxies));
    }

    public function downloadProxyList()
    {
        $proxy_list = file_get_contents('https://raw.githubusercontent.com/clarketm/proxy-list/master/proxy-list-raw.txt');
//        $proxy_list = file_get_contents('https://raw.githubusercontent.com/ShiftyTR/Proxy-List/master/socks5.txt');
        return explode("\n", $proxy_list);
    }

    public function updateProxyList()
    {
        $this->deleteBadPerformingProxies();
        $proxies = $this->downloadProxyList();
        $total = count($proxies);
        foreach ($proxies as $proxy) {
            if (!empty($proxy) && $this->test($proxy)) {
                $this->createProxyIfNotExists($proxy);
            }
        }
    }

    public function createProxyIfNotExists($proxy)
    {
        list($ip, $port) = explode(':', $proxy);
        if ($this->proxies->where('ip', $ip)->count() <= 0) {
            Log::info('[Scrappy] Added new proxy (' . $proxy . ')');
            $p = new \stdClass();
            $p->port = $port;
            $p->ip = $ip;
            $p->proxy_load_time = 0;
            $p->updated_at = Carbon::now()->toDateTimeString();
            $p->created_at = Carbon::now()->toDateTimeString();
            $this->proxies->add($p);
            $this->saveProxyList();
        }
    }

    public function deleteBadPerformingProxies()
    {
        $r = $this->proxies->where('proxy_load_time', '>=', 30);
        foreach ($r as $proxy => $item) {
            unset($this->proxies[$proxy]);
        }
    }

    public function test($proxy_plain)
    {
        $proxy = explode(':', $proxy_plain);
        $host = $proxy[0];
        $port = $proxy[1];

        $waitTimeoutInSeconds = 1;
        if($fp = @fsockopen($host,$port,$errCode,$errStr,$waitTimeoutInSeconds)){
            fclose($fp);
            return true;
        } else {
            return false;
        }
    }
}