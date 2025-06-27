<?php

declare(strict_types=1);

namespace JordanPartridge\GitHubZero\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RepoCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('repo')
            ->setDescription('Show available repository commands');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('');
        $output->writeln('<info>🏗️  Repository Commands</info>');
        $output->writeln('<comment>═══════════════════════</comment>');
        $output->writeln('');

        $commands = [
            'repo:list' => 'List repositories with interactive filtering and sorting',
            'repo:clone' => 'Clone repositories with enhanced selection',
            'repo:create' => 'Create a new repository (coming soon)',
            'repo:view' => 'View detailed repository information (coming soon)',
            'repo:fork' => 'Fork a repository (coming soon)',
            'repo:delete' => 'Delete a repository (coming soon)',
        ];

        foreach ($commands as $command => $description) {
            $available = str_ends_with($description, '(coming soon)') ? '⏳' : '✅';
            $cleanDescription = str_replace(' (coming soon)', '', $description);
            $output->writeln("  {$available} <info>{$command}</info>");
            $output->writeln("     {$cleanDescription}");
            $output->writeln('');
        }

        $output->writeln('<comment>Examples:</comment>');
        $output->writeln('  ghz repo:list --interactive');
        $output->writeln('  ghz repo:list --type=private --sort=updated');
        $output->writeln('  ghz repo:clone --interactive');
        $output->writeln('');

        return 0;
    }
}
