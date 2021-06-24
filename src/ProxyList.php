<?php


namespace Alr\Scrappy;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ProxyList
{
    public $proxies;

    public function __construct()
    {
        $this->proxies = \Cache::get('scrappy_proxies');

        if (empty($this->proxies)) {
            $this->proxies = collect([]);
            $this->updateProxyList();
        } else {
            $this->proxies = collect(json_decode($this->proxies));
        }
    }

    public function saveList()
    {
        \Cache::forever('scrappy_proxies', json_encode($this->proxies));
    }

    public function downloadProxyList()
    {
        $proxy_list = file_get_contents('https://raw.githubusercontent.com/clarketm/proxy-list/master/proxy-list-raw.txt');
        return explode("\n", $proxy_list);
    }

    public function updateProxyList()
    {
        $this->deleteBadPerformingProxies();

        foreach ($this->downloadProxyList() as $proxy) {
            Log::info('[Scrappy] Testing (' . $proxy . ') ');
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
            $this->saveList();
        }
    }

    public function deleteBadPerformingProxies()
    {
        $r = $this->proxies->where('proxy_load_time', '>=', 30);
        foreach ($r as $proxy => $item) {
            unset($this->proxies[$proxy]);
        }
    }

    public function test($proxy)
    {
        $proxy = explode(':', $proxy);
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