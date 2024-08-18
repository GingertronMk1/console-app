<?php

namespace App\Command;

use App\Kernel;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'app:parse-messages',
    description: 'Add a short description for your command',
)]
class ParseMessagesCommand extends Command
{
    private const ARG_FILE_PATH = 'filePath';
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly Kernel $app,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(self::ARG_FILE_PATH, InputArgument::REQUIRED, 'The file to parse')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = $input->getArgument(self::ARG_FILE_PATH);

        $fullFilePath = $this->app->getProjectDir() . '/' . $filePath;

        $io->info($fullFilePath);
        if ($this->filesystem->exists($fullFilePath)) {
            $messagesJson = $this->filesystem->readFile($fullFilePath);
            ['messages' => $messages] = json_decode($messagesJson, true);

            $modifiedMessages = array_map(
                fn (array $message) => [
                    $message['sender_name'],
                    \DateTimeImmutable::createFromFormat(
                        'U',
                        intdiv($message['timestamp_ms'], 1000)
                    )
                        ->format('Y-m-d H:i:s'),
                    wordwrap($message['content'] ?? 'Content unknown', 40)
                ],
                array_reverse($messages)
            );

            $io->table(
                ['Person', 'Time', 'Message'],
                $modifiedMessages
            );

            $this->filesystem->dumpFile(
                $this->app->getProjectDir() . '/output.txt',
                implode(
                    PHP_EOL.PHP_EOL.'---'.PHP_EOL.PHP_EOL,
                    array_map(
                        fn (array $message) => "{$message[0]} | {$message[1]}\n{$message[2]}",
                        $modifiedMessages
                    )
                )
            );
        }

        return Command::SUCCESS;
    }
}
