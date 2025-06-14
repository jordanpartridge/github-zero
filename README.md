# GitHub Zero

A lightweight GitHub CLI that works everywhere - standalone, in Laravel, Laravel Zero, or as a Conduit extension.

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

### Conduit Extension
```bash
conduit install github-zero
conduit github:repos
```

## Features

- 🚀 Interactive repository management
- 📋 Issue tracking
- 🔄 Pull request workflows  
- 🌟 Rich terminal UI with prompts
- 🔧 Works anywhere (standalone, Laravel, Conduit)

## Commands

- `github repos` - List and interact with repositories
- `github clone` - Clone repositories with selection
- `github issues` - Manage issues
- `github prs` - Handle pull requests

## Configuration

Set your GitHub token:
```bash
export GITHUB_TOKEN=your_token_here
```

## License

MIT License. See [LICENSE](LICENSE) for details.