<?php
namespace Alr\Scrappy;

use Alr\Scrappy\Exceptions\ScrappyException;
use App\Crawler\Scrappers\ScrapperInterface;
use Illuminate\Filesystem\Cache;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

class ScrapperFactory
{
    /** @var ScrapperInterface  */
    protected $scrapper;

    public function setScrapper(ScrapperInterface $scrapper)
    {
        $this->scrapper = $scrapper;
    }

    public function download($url)
    {
        return $this->scrapper->get($url);
    }
}