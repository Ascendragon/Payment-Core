<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:db:seed',
    description: 'Add a short description for your command',
)]
class DbSeedCommand extends Command
{
    public function __construct(private readonly Connection $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $totalAccounts = 1000000;
        $chunkSize = 5000;

        $io->title('Начинаем массовую генерацию данных(1 000 000 счетов)');
        $progressBar = new ProgressBar($output, $totalAccounts);
        $progressBar->start();

        $values = [];
        $params = [];

        $this->db->executeStatement('TRUNCATE TABLE account CASCADE');

        for ($i = 1; $i <= $totalAccounts; $i++) {
            $uuid = Uuid::v4()->toRfc4122();
            $balance = rand(100,100000). '.00';
            $currency = 'RUB';
            $version = 1;

            $values[] = '(?, ?, ?, ?)';
            $params[] = $uuid;
            $params[] = $balance;
            $params[] = $version;
            $params[] = $currency;

            if ($i % $chunkSize === 0) {
                $sql = "INSERT INTO account (id, balance, version,currency) VALUES ".implode(', ', $values);
                $this->db->executeStatement($sql, $params);
                $values = [];
                $params = [];

                $progressBar->advance($chunkSize);
            }
        }

        $progressBar->finish();
        $io->newLine(2);
        $io->success('Генерация успешно завершена!');

        return Command::SUCCESS;
    }
}
