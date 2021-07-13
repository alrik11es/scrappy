<?php


namespace Alr\Scrappy;

use Alr\Scrappy\Models\Proxy;
use Alr\Scrappy\Scrappers\ProxyCurlScrapper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProxyList
{
    public $proxies;
    private $selected_proxy;

    public function markUsedProxy()
    {
        Cache::forever('scrappy_used_proxy', implode(':', [$this->selected_proxy->ip, $this->selected_proxy->port]));
    }

    public function selectNextProxy()
    {
//        $this->deleteBadPerformingProxies();
//        if (count($this->proxies) == 0) {
//            $this->updateProxyList();
//        }

        $scrappy_used_proxy = Cache::get('scrappy_used_proxy');
        if($scrappy_used_proxy) {
            list($ip, $port) = explode(':', $scrappy_used_proxy);
            $valid_proxies = Proxy::where('ip', '!=', $ip)
                ->orWhere('port', '!=', $port)
                ->orderBy('proxy_load_time', 'asc')
                ->get();
        } else {
            $valid_proxies = Proxy::orderBy('proxy_load_time', 'asc')->get();
        }
        $this->selected_proxy = $valid_proxies->first();
        return $this->selected_proxy;
    }

    public function downloadProxyList()
    {
        $proxy_list = file_get_contents('https://raw.githubusercontent.com/clarketm/proxy-list/master/proxy-list-raw.txt');
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
        if (Proxy::where('ip', $ip)->where('port', $port)->count() <= 0) {
            Log::info('[Scrappy] Added new proxy (' . $proxy . ')');
            $p = new Proxy();
            $p->port = $port;
            $p->ip = $ip;
            $p->proxy_load_time = 0;
            $p->save();
        }
    }

    public function deleteBadPerformingProxies()
    {
        $r = Proxy::where('proxy_load_time', '>=', 30)->get();
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