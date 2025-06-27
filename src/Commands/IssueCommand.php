<?php

declare(strict_types=1);

namespace JordanPartridge\GitHubZero\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class IssueCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('issue')
            ->setDescription('Show available issue commands');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('');
        $output->writeln('<info>🐛 Issue Commands</info>');
        $output->writeln('<comment>══════════════════</comment>');
        $output->writeln('');

        $commands = [
            'issue:list' => 'List issues with advanced filtering and interactive selection',
            'issue:create' => 'Create new issues with templates (coming soon)',
            'issue:show' => 'View detailed issue information (coming soon)',
            'issue:close' => 'Close issues (coming soon)',
            'issue:comment' => 'Add comments to issues (coming soon)',
        ];

        foreach ($commands as $command => $description) {
            $available = str_ends_with($description, '(coming soon)') ? '⏳' : '✅';
            $cleanDescription = str_replace(' (coming soon)', '', $description);
            $output->writeln("  {$available} <info>{$command}</info>");
            $output->writeln("     {$cleanDescription}");
            $output->writeln('');
        }

        $output->writeln('<comment>Examples:</comment>');
        $output->writeln('  ghz issue:list laravel/framework');
        $output->writeln('  ghz issue:list owner/repo --state=closed --limit=20');
        $output->writeln('  ghz issue:list owner/repo --assignee=username --stats');
        $output->writeln('  ghz issue:list owner/repo --query="open bugs" --interactive');
        $output->writeln('');

        return 0;
    }
}
