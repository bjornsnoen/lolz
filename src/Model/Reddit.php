<?php
/**
 * @license Proprietary
 * @author bjorn
 * @copyright BRBcoffee 2020
 */


namespace App\Model;

use App\Entity\Lol;

/**
 * Class Reddit
 * @author bjorn
 */
class Reddit
{
    /**
     * @var \Twig\Environment
     */
    private $twigEnv;

    public function __construct(\Twig\Environment $twigEnv)
    {
        $this->twigEnv = $twigEnv;
    }

    /**
     * @param Lol $lol
     * @return bool
     * @author bjorn
     */
    public function isVideo(Lol $lol): bool
    {
        return strpos($lol->getImageUrl(), 'v.redd.it') !== false;
    }

    public function isNotImage(Lol $lol): bool
    {
        $url = $lol->getImageUrl();
        return !pathinfo($url, PATHINFO_EXTENSION) && strpos($url, 'reddit.com') !== false;
    }

    /**
     * @param Lol $lol
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @author bjorn
     */
    public function embedComment(Lol $lol): string
    {
        $url = $lol->getUrl();
        $parts = parse_url($url);
        preg_match('/\/r\/(.+)\/comments\/(.+)\/(.+)\//', $parts['path'], $matches);
        list ($path, $subreddit, $username, $title) = $matches;
        return $this->twigEnv->render('lolz/reddit-video.html.twig', [
            'commentUrl' => $url,
            'subredditLink' => sprintf('https://www.reddit.com/r/%s/', $subreddit),
            'subredditName' => $subreddit
        ]);
    }
}
