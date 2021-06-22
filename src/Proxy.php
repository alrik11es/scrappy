<?php
namespace Alr\Scrappy;

use Illuminate\Filesystem\Cache;
use Psr\Log\LoggerInterface;

class Proxy
{
    public $port;
    public $ip;
    public $proxy_load_time = 60;
}