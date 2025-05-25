<?php

declare(strict_types=1);

namespace App\Command;

use App\EventConsumer\EventConsumer;
use App\Service\EventSourceRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:consume-events',
    description: 'Start the event consumer process',
)]
class EventConsumerCommand extends Command
{
    public function __construct(
        private readonly EventConsumer $eventConsumer,
        private readonly EventSourceRegistry $eventSourceRegistry,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->info('Starting event consumer process');

        try {
            $this->eventConsumer->run();
            // This line should never be reached as run() contains an infinite loop
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Event consumer process failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
