<?php
namespace App\Crawler\Scrappers;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

interface ScrapperInterface
{
    public function get($url);
}