<?php
namespace Alr\Scrappy\Scrappers;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

interface ScrapperInterface
{
    public function get($url);
    public function declareInvalidByRegex($invalid_regex = []);
}