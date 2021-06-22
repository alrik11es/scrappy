<?php


namespace Alr\Scrappy;

use Illuminate\Filesystem\Cache;
use Illuminate\Support\Facades\Log;

class ProxyList
{
    public $proxies;

    public function __construct()
    {
        $this->proxies = Cache::get('scrappy_proxies');

        if (empty($proxies)) {
            $this->proxies = collect([]);
            $this->updateProxyList();
        }
    }

    public function saveList()
    {
        Cache::remember(120, 'scrappy_proxies', function() {
            return json_encode($this->proxies);
        });
    }

    public function updateProxyList()
    {
        $this->proxies->where('proxy_load_time', '>=',30)->delete();
        $proxy_list = file_get_contents('https://raw.githubusercontent.com/clarketm/proxy-list/master/proxy-list-raw.txt');
        $proxy_list = explode("\n", $proxy_list);
        foreach ($proxy_list as $proxy) {
            Log::info('[Scrappy] Testing ('.$proxy.') ');
            if(!empty($proxy) && $this->test($proxy)) {
                list($ip, $port) = explode(':', $proxy);
                if ($this->proxies->where('ip', $proxy->ip)->count() <= 0 ) {
                    Log::info('[Scrappy] Added new proxy ('.$proxy.')');
                    $p = new Proxy();
                    $p->port = $port;
                    $p->ip = $ip;
                    $this->proxies->add($p);
                    $this->saveList();
                }
            }
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