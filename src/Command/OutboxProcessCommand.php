<?php

namespace App\Command;

use App\Contracts\MessageBrokerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

#[AsCommand(name:'app:outbox:process',description:'Отправляет сообщения из Outbox в RabbitMQ')]
class OutboxProcessCommand extends Command
{
    public function __construct(private Connection $db,private MessageBrokerInterface $messageBroker,private SerializerInterface $serializer)
    {
        parent::__construct();
    }
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $sql = "SELECT * FROM outbox_message WHERE status = 'Pending' LIMIT 50";
        $messages = $this->db->fetchAllAssociative($sql);
        foreach($messages as $row) {
            $message = $this->serializer->deserialize($row['payload'], $row['event_class'], 'json');
            $this->messageBroker->dispatch($message);
            $this->db->executeStatement("UPDATE outbox_message SET status = 'Sent' WHERE id = :id",['id' => $row['id']]);
            $output->writeln("Сообщение {$row['id']} успешно отправлено в брокер.");
        }

        return Command::SUCCESS;
    }

}
