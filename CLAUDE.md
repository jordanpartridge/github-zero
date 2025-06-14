# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Multi-Platform GitHub CLI Package

GitHub Zero is a lightweight GitHub CLI package designed to work across multiple environments:
- **Standalone CLI**: Global Composer installation with `bin/github` executable
- **Laravel Package**: Laravel service provider with Artisan commands
- **Conduit Extension**: Integration with the Conduit framework

## Development Commands

```bash
# Install dependencies
composer install

# Code quality and testing
./vendor/bin/pint          # Laravel PHP formatter
./vendor/bin/phpstan analyze   # Static analysis
./vendor/bin/pest          # Run tests (Pest framework)

# Test the CLI locally
./bin/github repos --interactive
./bin/github clone --interactive
```

## Architecture

### Core Components

- **Application.php**: Standalone console application entry point
- **GitHubZeroServiceProvider.php**: Laravel service provider for Artisan integration  
- **ConduitExtension.php**: Conduit framework extension registration
- **Commands/**: Contains CLI command implementations using Illuminate Console

### Multi-Environment Design

The package uses a unified command architecture where the same command classes work across all three environments:
- Commands extend `Illuminate\Console\Command` for consistency
- GitHub client is injected via constructor dependency injection
- Interactive prompts use `Laravel\Prompts` for rich terminal UI

### GitHub Integration

- Uses `jordanpartridge/github-client` package for GitHub API operations
- Commands require `GITHUB_TOKEN` environment variable
- Supports repository listing, cloning, and interactive selection

## Configuration

Set GitHub token for API access:
```bash
export GITHUB_TOKEN=your_token_here
```

## Command Structure

Commands follow a consistent pattern:
- Token validation in `handle()` method
- Interactive prompts when `--interactive` flag is used
- Rich terminal output with emojis and formatting
- Error handling with user-friendly messages

The CLI supports both direct argument usage and interactive selection modes.