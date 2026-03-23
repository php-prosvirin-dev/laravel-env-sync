<?php

namespace Prosvirin\EnvSync\Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

uses(TestCase::class);

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/env-sync-test';

    if (! File::exists($this->tempDir)) {
        File::makeDirectory($this->tempDir);
    }

    $this->originalBasePath = base_path();

    $this->app->setBasePath($this->tempDir);

    File::put($this->tempDir.'/.env.example',
        'APP_NAME="Test App"'."\n".
        'APP_ENV=local'."\n".
        'APP_DEBUG=true'."\n".
        'DB_HOST=localhost'."\n".
        'DB_PORT=3306'
    );

    File::put($this->tempDir.'/.env',
        'APP_NAME="Test App"'."\n".
        'APP_ENV=production'."\n".
        'DB_HOST=127.0.0.1'
    );
});

afterEach(function () {
    $this->app->setBasePath($this->originalBasePath);

    if (File::exists($this->tempDir)) {
        File::deleteDirectory($this->tempDir);
    }
});

test('creates env file when it does not exist', function () {
    File::delete($this->tempDir.'/.env');

    Artisan::call('env:sync');

    $this->assertTrue(File::exists($this->tempDir.'/.env'));

    $content = File::get($this->tempDir.'/.env');
    expect($content)->toContain('APP_NAME="Test App"');
});

test('shows missing variables', function () {
    $exitCode = Artisan::call('env:sync');

    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('DB_PORT')
        ->and($output)->toContain('APP_DEBUG');
});

test('adds missing variables automatically', function () {
    Artisan::call('env:sync');

    $content = File::get($this->tempDir.'/.env');

    expect($content)->toContain('DB_PORT=3306')
        ->and($content)->toContain('APP_DEBUG=true')
        ->and($content)->toContain('APP_ENV=production')
        ->and($content)->toContain('DB_HOST=127.0.0.1');
});

test('does not overwrite existing variables', function () {
    Artisan::call('env:sync');

    $content = File::get($this->tempDir.'/.env');

    expect($content)->toContain('APP_ENV=production')
        ->and($content)->not->toContain('APP_ENV=local')
        ->and($content)->toContain('DB_HOST=127.0.0.1')
        ->and($content)->not->toContain('DB_HOST=localhost');
});

test('shows warning for extra variables that exist in env but not in example', function () {
    File::put($this->tempDir.'/.env',
        'APP_NAME="Test App"'."\n".
        'APP_ENV=production'."\n".
        'CUSTOM_VAR=some_value'."\n".
        'ANOTHER_VAR=another_value'
    );

    Artisan::call('env:sync');

    $output = Artisan::output();

    expect($output)->toContain('CUSTOM_VAR')
        ->and($output)->toContain('ANOTHER_VAR')
        ->and($output)->toContain('Variables found in .env but missing in .env.example');
});

test('shows success message when files are synchronized', function () {
    File::put($this->tempDir.'/.env',
        'APP_NAME="Test App"'."\n".
        'APP_ENV=local'."\n".
        'APP_DEBUG=true'."\n".
        'DB_HOST=localhost'."\n".
        'DB_PORT=3306'
    );

    Artisan::call('env:sync');

    $output = Artisan::output();

    expect($output)->toContain('already synchronized');
});

test('force sync replaces env file and creates backup', function () {
    $originalContent = File::get($this->tempDir.'/.env');

    Artisan::call('env:sync', ['--force' => true]);

    $newContent = File::get($this->tempDir.'/.env');

    expect($newContent)->toContain('APP_ENV=local')
        ->and($newContent)->toContain('APP_DEBUG=true')
        ->and($newContent)->toContain('DB_PORT=3306');

    $backupFiles = glob($this->tempDir.'/.env.backup.*');
    expect($backupFiles)->not->toBeEmpty();

    $backupContent = File::get($backupFiles[0]);
    expect($backupContent)->toBe($originalContent);
});

test('adds new variable interactively', function () {
    $this->artisan('env:sync', ['--add' => true])
        ->expectsQuestion('Enter variable name', 'API_KEY')
        ->expectsQuestion('Enter variable value', 'secret123')
        ->expectsOutputToContain('API_KEY')
        ->assertExitCode(0);

    $exampleContent = File::get($this->tempDir.'/.env.example');
    $envContent = File::get($this->tempDir.'/.env');

    expect($exampleContent)->toContain('API_KEY=****')
        ->and($envContent)->toContain('API_KEY=secret123');
});

test('prevents adding duplicate variable', function () {
    $this->artisan('env:sync', ['--add' => true])
        ->expectsQuestion('Enter variable name', 'APP_NAME')
        ->expectsOutputToContain('already exists')
        ->assertExitCode(1);
});

test('validates variable name format', function () {
    $this->artisan('env:sync', ['--add' => true])
        ->expectsQuestion('Enter variable name', '123-invalid')
        ->expectsOutputToContain('must start with a letter')
        ->assertExitCode(1);
});

test('removes variable interactively', function () {
    File::put($this->tempDir.'/.env.example',
        'APP_NAME="Test App"'."\n".
        'API_KEY=****'
    );

    File::put($this->tempDir.'/.env',
        'APP_NAME="Test App"'."\n".
        'API_KEY=secret123'
    );

    $this->artisan('env:sync', ['--remove' => true])
        ->expectsChoice('Select variable to remove', 'API_KEY', ['APP_NAME', 'API_KEY'])
        ->expectsConfirmation('Are you sure you want to remove \'API_KEY\' from both files?', 'yes')
        ->expectsOutputToContain('removed successfully')
        ->assertExitCode(0);

    $exampleContent = File::get($this->tempDir.'/.env.example');
    $envContent = File::get($this->tempDir.'/.env');

    expect($exampleContent)->not->toContain('API_KEY')
        ->and($envContent)->not->toContain('API_KEY');
});

test('cancels removal when user declines', function () {
    File::put($this->tempDir.'/.env.example',
        'APP_NAME="Test App"'."\n".
        'API_KEY=****'
    );

    File::put($this->tempDir.'/.env',
        'APP_NAME="Test App"'."\n".
        'API_KEY=secret123'
    );

    $this->artisan('env:sync', ['--remove' => true])
        ->expectsChoice('Select variable to remove', 'API_KEY', ['APP_NAME', 'API_KEY'])
        ->expectsConfirmation('Are you sure you want to remove \'API_KEY\' from both files?', 'no')
        ->expectsOutputToContain('cancelled')
        ->assertExitCode(0);

    $exampleContent = File::get($this->tempDir.'/.env.example');
    $envContent = File::get($this->tempDir.'/.env');

    expect($exampleContent)->toContain('API_KEY')
        ->and($envContent)->toContain('API_KEY');
});

test('handles missing env file gracefully', function () {
    File::delete($this->tempDir.'/.env');

    $exitCode = Artisan::call('env:sync');

    $output = Artisan::output();

    expect($exitCode)
        ->toBe(0)
        ->and($output)->toContain('Creating from')
        ->and(File::exists($this->tempDir.'/.env'))
        ->toBeTrue();
});

test('handles missing example file gracefully', function () {
    File::delete($this->tempDir.'/.env.example');

    $exitCode = Artisan::call('env:sync');

    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('not found');
});
