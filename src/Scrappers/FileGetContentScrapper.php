<?php
namespace Alr\Scrappy\Scrappers;

class FileGetContentScrapper implements ScrapperInterface
{
    private $invalid_regex = [];

    public function declareInvalidByRegex($invalid_regex = [])
    {
        $this->invalid_regex = $invalid_regex;
    }

    public function get($url)
    {
        $scraped_page = file_get_contents($url);
        foreach ($this->invalid_regex as $regex) {
            if (preg_match($regex, $scraped_page)) {
                $scraped_page = null;
            }
        }
        return $scraped_page;
    }
}