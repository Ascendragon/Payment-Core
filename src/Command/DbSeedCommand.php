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
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:db:seed',
    description: 'Add a short description for your command',
)]
class DbSeedCommand extends Command
{
    public function __construct(private readonly Connection $db, private readonly KernelInterface $kernel)
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
        $env = $this->kernel->getEnvironment();
        if (!in_array($env, ['dev', 'test'], true)) {
            $io->error('Сид запрещен вне dev/test');

            return Command::FAILURE;
        }

        $totalAccounts = 1000000;
        $chunkSize = 5000;

        $io->title('Начинаем массовую генерацию данных(1 000 000 счетов)');
        $progressBar = new ProgressBar($output, $totalAccounts);
        $progressBar->start();

        $values = [];
        $params = [];

        $this->db->executeStatement('TRUNCATE TABLE app_user CASCADE');

        $userIds = [];
        $userCount = 3;
        for ($u = 0; $u < $userCount; ++$u) {
            $id = Uuid::v4()->toRfc4122();
            $token = bin2hex(random_bytes(32));
            $hash = hash('sha256', $token);

            $this->db->executeStatement("INSERT INTO app_user(id,name,api_token_hash) VALUES(?, ?, ?)", [$id, "demo-user-$u", $hash]);
            $userIds[] = $id;
            $io->writeln(sprintf('user=%s token=<comment>%s</comment>', $userIds[$u], $token));
        }

        for ($i = 1; $i <= $totalAccounts; ++$i) {
            $uuid = Uuid::v4()->toRfc4122();
            $balance = rand(100, 100000).'.00';
            $currency = 'RUB';
            $version = 1;
            $ownerId  = $userIds[$i % $userCount];

            $values[] = '(?, ?, ?, ?,?)';
            $params[] = $uuid;
            $params[] = $balance;
            $params[] = $version;
            $params[] = $currency;
            $params[] = $ownerId;

            if (0 === $i % $chunkSize) {
                $sql = 'INSERT INTO account (id, balance, version,currency,owner_id) VALUES '.implode(', ', $values);
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
