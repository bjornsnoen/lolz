<?php
/**
 * @license Proprietary
 * @author Bjørn Snoen <bjorn.snoen@gmail.com>
 * @copyright BRBcoffee 2020
 */

namespace App\Model\Parser;


use App\Entity\Lol;
use App\Model\Api\ParserAbstract;
use PHPHtmlParser\Dom;
use PHPHtmlParser\Exceptions\ChildNotFoundException;
use PHPHtmlParser\Exceptions\CircularException;
use PHPHtmlParser\Exceptions\NotLoadedException;
use PHPHtmlParser\Exceptions\StrictException;
use Zend\Feed\Reader\Entry\EntryInterface;
use Zend\Feed\Reader\Feed\FeedInterface;
use Zend\Feed\Reader\Reader;

/**
 * Class GenericParser
 * @author Bjørn Snoen <bjorn.snoen@gmail.com>
 */
class GenericParser extends ParserAbstract
{
    /**
     * @var FeedInterface
     */
    protected $feed;

    /**
     * @return Lol|null
     * @throws ChildNotFoundException
     * @author Bjørn Snoen <bjorn.snoen@gmail.com>
     */
    public function next(): ?Lol
    {
        $feed = $this->getFeed();
        /** @var EntryInterface $next */
        try {
            $next = $feed->current();
        } catch (\ErrorException $exception) {
            return null;
        } catch (\TypeError $exception) {
            return null;
        }
        $feed->next();

        $content = $next->getContent();
        $dom = new Dom();
        try {
            $dom->loadStr($content);
        } catch (\Exception $e) {
            return $this->next();
        }
        $videos = $this->getVideoSources($dom);

        $lol = new Lol();
        if ($videos) {
            $lol->setVideoSources($videos)->setImageUrl($next->getLink());
        } else {
            $lol->setImageUrl($this->getImageUrl($dom));
            $lol->setCaption($this->getImageTitle($dom));
        }

        $lol->setFetched($this->getNow())->setUrl($next->getLink())->setTitle($next->getTitle());
        return $lol;
    }

    /**
     * @param Dom $dom
     * @return array
     * @author Bjørn Snoen <bjorn.snoen@gmail.com>
     */
    private function getVideoSources(Dom $dom): array
    {
        try {
            $videos = $dom->find('video');
        } catch (\Exception $e) {
            return [];
        }
        $returnSources = [];

        if (!empty($videos) && $videos->count() > 0) {
            /** @var Dom\HtmlNode $video */
            $video = $videos->getIterator()->current();
            try {
                $sources = $video->find('source');
            } catch (ChildNotFoundException $e) {
                return [];
            }
            /** @var Dom\HtmlNode $source */
            foreach ($sources as $source) {
                $returnSources[] = [
                    'src' => $source->getTag()->getAttribute('src')['value'],
                    'type' => $source->getTag()->getAttribute('type')['value']
                ];
            }
        }

        return $returnSources;
    }

    private function getImageUrl(Dom $dom): string
    {
        $img = $this->getImage($dom);
        if (is_null($img)) {
            return "";
        }
        return $img->getTag()->getAttribute('src')['value'];
    }

    private function getImageTitle(Dom $dom): ?string
    {
        $img = $this->getImage($dom);
        if (is_null($img)) {
            return null;
        }
        $title = $img->getTag()->getAttribute('title') ?? $img->getTag()->getAttribute('alt');

        return $title ? $title['value'] : '';
    }

    /**
     * @param Dom $dom
     * @return Dom\HtmlNode|null
     * @author Bjørn Snoen <bjorn.snoen@gmail.com>
     */
    private function getImage(Dom $dom): ?Dom\HtmlNode
    {
        try {
            $imgs = $dom->find('img');
        } catch (\Exception $e) {
            return null;
        }
        if ($imgs->count() == 0) {
            return null;
        }
        /** @var Dom\HtmlNode $img */
        $img = $imgs->getIterator()->current();
        return $img;
    }

    /**
     * @return FeedInterface
     * @author Bjørn Snoen <bjorn.snoen@gmail.com>
     */
    protected function getFeed()
    {
        if (isset($this->feed)) {
            return $this->feed;
        }
        $feedContents = $this->getResponse()->getBody()->getContents();
        $feed = Reader::importString($feedContents);
        $this->feed = $feed;
        return $feed;
    }
}
