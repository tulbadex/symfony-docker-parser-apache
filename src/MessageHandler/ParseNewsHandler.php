<?php
namespace App\MessageHandler;

use App\Message\ParseNewsMessage;
use App\Service\NewsParser;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Psr\Log\LoggerInterface;

class ParseNewsHandler implements MessageHandlerInterface
{
    private $newsParser;
    private $logger;

    public function __construct(NewsParser $newsParser, LoggerInterface $logger)
    {
        $this->newsParser = $newsParser;
        $this->logger = $logger;
    }

    public function __invoke(ParseNewsMessage $message)
    {
        try {
            $this->logger->info('Starting news parsing for source: ' . $message->getSource());
            
            $result = $this->newsParser->parse($message->getSource());
            
            if ($result) {
                $this->logger->info('News parsing completed successfully');
            } else {
                $this->logger->warning('News parsing did not find any items');
            }
        } catch (\Exception $e) {
            $this->logger->error('News parsing failed: ' . $e->getMessage());
            throw $e; // Will trigger retry mechanism
        }
    }
}