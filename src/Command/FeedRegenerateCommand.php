<?php

namespace App\Command;

use App\Entity\Feed;
use App\Model\ParserRepository;
use App\Repository\FeedRepository;
use App\Repository\LolRepository;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class FeedRegenerateCommand extends Command
{
    protected static $defaultName = 'feed:regenerate';
    /**
     * @var ParserRepository
     */
    private $parserRepository;
    /**
     * @var FeedRepository
     */
    private $feedRepository;
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var PromiseInterface[]
     */
    private $promises;
    /**
     * @var LolRepository
     */
    private $lolRepository;

    public function __construct(
        ParserRepository $parserRepository,
        FeedRepository $feedRepository,
        EntityManagerInterface $entityManager,
        LolRepository $lolRepository
    ) {
        parent::__construct(self::$defaultName);
        $this->parserRepository = $parserRepository;
        $this->feedRepository = $feedRepository;
        $this->entityManager = $entityManager;
        $this->lolRepository = $lolRepository;
    }

    protected function configure()
    {
        $this->setDescription('Get all feeds and dump');
        $this->addOption(
            'feed',
            null,
            InputOption::VALUE_REQUIRED,
            'Download only this feed'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$input->getOption('feed')) {
            $feeds = $this->feedRepository->findAll();
        } else {
            $feeds = $this->feedRepository->findByUrlPart($input->getOption('feed'));
        }
        if (empty($feeds)) {
            $io = new SymfonyStyle($input, $output);
            $io->error(sprintf('No feed found similar to "%s"', $input->getOption('feed')));
            return 1;
        }

        foreach ($feeds as $feed) {
            $this->fetch(
                $feed,
                function (ResponseInterface $result) use ($feed) {
                    $parser = $this->parserRepository->getParserForFeed($feed, $result);
                    while ($lol = $parser->next()) {
                        if (!$this->lolRepository->alreadyExists($lol)) {
                            $this->entityManager->persist($lol);
                        }
                    }
                }
            );
        }

        foreach ($this->promises as $promise) {
            try {
                $promise->wait();
            } catch (RequestException $exception) {
                $a = 1;
            }
        }
        $this->entityManager->flush();
    }

    protected function fetch(Feed $feed, \Closure $onFetched)
    {
        $promise = $this->getClient()->requestAsync(
            'GET',
            $feed->getUrl()
        );

        $promise->then(
            $onFetched,
            function () {
            }
        );
        $this->promises[] = $promise;
    }

    protected function getClient(): ClientInterface
    {
        if (!isset($this->client)) {
            $this->client = new Client([
                RequestOptions::HEADERS => [
                    'User-Agent' => 'bjorn lolz generator'
                ]
            ]);
        }
        return $this->client;
    }
}
