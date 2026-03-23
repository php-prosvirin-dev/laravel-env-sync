# Laravel Env Sync

A Laravel package that synchronizes your .env.example with .env file.

## Features

- Auto-sync - Adds missing variables from .env.example to .env
- No overwrites - Preserves existing values
- Extra variables detection
- Backup support before force sync
- Interactive add new variables
- Interactive remove variables
- Force sync with backup

## Requirements

- PHP 8.1 or higher
- Laravel 10.x or 11.x

## Installation
```bash
composer require php-prosvirin-dev/laravel-env-sync --dev
```

## Usage

Basic sync:
```bash
php artisan env:sync
```

Force sync with backup:
```bash
php artisan env:sync --force
```

Add new variable:
```bash
php artisan env:sync --add
```

Remove variable:
```bash
php artisan env:sync --remove
```

## CI/CD Integration

Use this package in your CI/CD pipeline to ensure .env files are always in sync with .env.example:

```yaml
name: Check Environment Consistency

on: [push, pull_request]

jobs:
  check-env:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
          
      - name: Install Dependencies
        run: composer install
        
      - name: Check .env Consistency
        run: |
          php artisan env:sync
          git diff --exit-code
```

## License
MIT