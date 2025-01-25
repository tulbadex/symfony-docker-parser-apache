<?php
namespace App\Command;

use App\Message\ParseNewsMessage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class ParseNewsCommand extends Command
{
    protected static $defaultName = 'app:parse-news';
    private $messageBus;

    public function __construct(MessageBusInterface $messageBus)
    {
        $this->messageBus = $messageBus;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Dispatches a news parsing message')
            ->setHelp('This command sends a message to parse news');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Dispatching news parsing message...');
        
        try {
            $message = new ParseNewsMessage();
            $this->messageBus->dispatch($message);
            
            $output->writeln('News parsing message sent successfully!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Failed to dispatch message: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}