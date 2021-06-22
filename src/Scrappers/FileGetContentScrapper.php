<?php
namespace Alr\Scrappy\Scrappers;

use App\Crawler\Scrappers\ScrapperInterface;

class FileGetContentScrapper implements  ScrapperInterface
{
    private $logger;
    private $cache;

    /**
     * @param mixed $logger
     */
    public function setLogger($logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @param mixed $cache
     */
    public function setCache($cache): void
    {
        $this->cache = $cache;
    }

    public function get($url)
    {
        // TODO: Implement get() method.
    }
}