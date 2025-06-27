<?php

declare(strict_types=1);

namespace JordanPartridge\GitHubZero\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('list')
            ->setDescription('List all available GitHub Zero commands');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('');
        $output->writeln('<info>🐙 GitHub Zero - Available Commands</info>');
        $output->writeln('<comment>═══════════════════════════════════════</comment>');
        $output->writeln('');

        $commands = [
            'REPOSITORY COMMANDS' => [
                'repo:list' => 'List repositories with interactive filtering',
                'repo:clone' => 'Clone repositories with enhanced selection',
                'repo:create' => 'Create a new repository (coming soon)',
                'repo:view' => 'View repository details (coming soon)',
            ],
            'ISSUE COMMANDS' => [
                'issue:list' => 'List issues with advanced filtering',
                'issue:create' => 'Create new issues (coming soon)',
                'issue:show' => 'View issue details (coming soon)',
            ],
            'PULL REQUEST COMMANDS' => [
                'pr:list' => 'List pull requests (coming soon)',
                'pr:create' => 'Create pull requests (coming soon)',
                'pr:review' => 'Review pull requests (coming soon)',
            ],
            'UTILITY COMMANDS' => [
                'list' => 'Show this command list',
                'auth:status' => 'Check authentication status (coming soon)',
            ],
        ];

        foreach ($commands as $category => $categoryCommands) {
            $output->writeln("<comment>{$category}</comment>");
            foreach ($categoryCommands as $command => $description) {
                $available = str_ends_with($description, '(coming soon)') ? '⏳' : '✅';
                $cleanDescription = str_replace(' (coming soon)', '', $description);
                $output->writeln("  {$available} <info>{$command}</info> - {$cleanDescription}");
            }
            $output->writeln('');
        }

        $output->writeln('<comment>💡 Tips:</comment>');
        $output->writeln('  • Use --interactive flag for enhanced prompts');
        $output->writeln('  • All commands support --help for detailed options');
        $output->writeln('  • Set GITHUB_TOKEN environment variable');
        $output->writeln('');

        return 0;
    }
}
