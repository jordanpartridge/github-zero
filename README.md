# GitHub Zero

A lightweight GitHub CLI that works everywhere - standalone, in Laravel, Laravel Zero, or as a Conduit extension (coming soon).

## Installation

### Standalone CLI
```bash
composer global require jordanpartridge/github-zero
github repos --interactive
```

### Laravel Package  
```bash
composer require jordanpartridge/github-zero
php artisan github:repos
```

### Conduit Extension (Coming Soon)
```bash
# Will be available when Conduit framework is released
conduit install github-zero
conduit github:repos
```

## Features

- 🚀 Interactive repository management
- 📋 Issue tracking (in development)
- 🔄 Pull request workflows  
- 🌟 Rich terminal UI with prompts
- 🔧 Works anywhere (standalone, Laravel, Conduit)

## Commands

- `github repos` - List and interact with repositories
- `github clone` - Clone repositories with selection
- `github issues` - Manage issues (requires github-client v2.1+)
- `github prs` - Handle pull requests (coming soon)

## Configuration

Set your GitHub token:
```bash
export GITHUB_TOKEN=your_token_here
```

## License

MIT License. See [LICENSE](LICENSE) for details.