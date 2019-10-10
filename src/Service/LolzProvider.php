<?php
/**
 * @license Proprietary
 * @author Bjørn Snoen <bjorn.snoen@visma.com>
 * @copyright Visma Digital Commerce 2019
 */

namespace App\Service;


use App\Entity\Lol;
use App\Model\Imgur;
use App\Model\Twitter;
use App\Model\Youtube;
use App\Repository\LolRepository;

class LolzProvider
{
    /**
     * @var LolRepository
     */
    private $lolRepository;
    /**
     * @var Twitter
     */
    private $twitter;

    /** @var Lol[] */
    private $lolz;
    /**
     * @var Youtube
     */
    private $youtube;
    /**
     * @var Imgur
     */
    private $imgur;

    public function __construct(
        LolRepository $lolRepository,
        Twitter $twitter,
        Youtube $youtube,
        Imgur $imgur
    ) {
        $this->lolRepository = $lolRepository;
        $this->twitter = $twitter;
        $this->youtube = $youtube;
        $this->imgur = $imgur;
    }

    public function next(): \Generator
    {
        foreach ($this->getLolz() as $lol) {
            if ($this->twitter->isTweet($lol)) {
                $lol->setContent($this->twitter->getContent($lol));
            } elseif ($this->youtube->isYoutube($lol)) {
                $lol->setContent($this->youtube->getContent($lol));
            } elseif ($this->imgur->isImgur($lol)) {
                $lol->setContent($this->imgur->getContent($lol));
            }
            yield $lol;
        }
    }

    /**
     * @return Lol[]
     * @author Bjørn Snoen <bjorn.snoen@visma.com>
     */
    protected function getLolz(): array
    {
        if (!isset($this->lolz)) {
            $lolz = $this->lolRepository->findBy([], ['fetched' => 'DESC']);
            return $lolz;
        }
        return $this->lolz;
    }
}