<?php

namespace Prosvirin\EnvSync\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Prosvirin\EnvSync\Classes\EnvComparator;
use Prosvirin\EnvSync\Classes\EnvDocumentor;
use Prosvirin\EnvSync\Classes\EnvParser;

class EnvSyncCommand extends Command
{
    protected $signature = 'env:sync
                            {--force : Replace .env with .env.example content, create backup}
                            {--add : Add new variable to both files}
                            {--remove : Remove variable from both files}';

    protected $description = 'Sync .env.example with .env file';

    private EnvParser $parser;

    private EnvComparator $comparator;

    private EnvDocumentor $documentor;

    public function __construct()
    {
        parent::__construct();
        $this->parser = new EnvParser;
        $this->comparator = new EnvComparator;
        $this->documentor = new EnvDocumentor;
    }

    public function handle(): int
    {
        if ($this->option('add')) {
            return $this->addVariable();
        }

        if ($this->option('remove')) {
            return $this->removeVariable();
        }

        $examplePath = base_path('.env.example');
        $envPath = base_path('.env');

        if ($this->option('force')) {
            return $this->forceSync($examplePath, $envPath);
        }

        return $this->normalSync($examplePath, $envPath);
    }

    private function normalSync(string $examplePath, string $envPath): int
    {
        if (! File::exists($examplePath)) {
            $this->error('.env.example file not found!');

            return 1;
        }

        if (! File::exists($envPath)) {
            $this->warn('.env file not found. Creating from .env.example...');
            File::copy($examplePath, $envPath);
            $this->info('.env file created successfully!');

            return 0;
        }

        $exampleVars = $this->parser->parse($examplePath);
        $envVars = $this->parser->parse($envPath);

        $diff = $this->comparator->compare($exampleVars, $envVars);

        if ($this->comparator->isSynchronized($exampleVars, $envVars)) {
            $this->info('Files are already synchronized!');

            return 0;
        }

        $missing = $diff['missing'];
        $extra = $diff['extra'];
        $mismatch = $diff['mismatch'];

        if (! empty($missing)) {
            $this->info('Adding missing variables from .env.example:');
            foreach ($missing as $key => $info) {
                $this->line("  + {$key}={$info['example']}");
            }

            $envContent = File::get($envPath);
            foreach ($missing as $key => $info) {
                $envContent = $this->parser->addVariable($envContent, $key, $info['example']);
            }

            File::put($envPath, $envContent);
            $this->info('Missing variables added successfully!');
        }

        if (! empty($extra)) {
            $this->warn('Variables found in .env but missing in .env.example:');
            foreach ($extra as $key => $value) {
                $this->line("  - {$key}={$value}");
            }
            $this->warn('Consider adding these to .env.example or remove them.');
        }

        if (! empty($mismatch)) {
            $this->warn('Variables with different values:');
            foreach ($mismatch as $key => $values) {
                $this->line("  • {$key}:");
                $this->line("      .env.example: {$values['example']}");
                $this->line("      .env:         {$values['env']}");
            }
        }

        return 0;
    }

    private function forceSync(string $examplePath, string $envPath): int
    {
        if (! File::exists($examplePath)) {
            $this->error('.env.example file not found!');

            return 1;
        }

        if (File::exists($envPath)) {
            $backupPath = $envPath.'.backup.'.date('Y-m-d_H-i-s');
            File::copy($envPath, $backupPath);
            $this->info("Backup created: {$backupPath}");
        }

        File::copy($examplePath, $envPath);
        $this->info('.env file replaced with .env.example content!');

        return 0;
    }

    private function addVariable(): int
    {
        $examplePath = base_path('.env.example');
        $envPath = base_path('.env');

        if (! File::exists($examplePath)) {
            $this->error('.env.example file not found!');

            return 1;
        }

        $key = $this->ask('Enter variable name');

        if (empty($key)) {
            $this->error('Variable name cannot be empty!');

            return 1;
        }

        if (! preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $key)) {
            $this->error('Variable name must start with a letter and contain only letters, numbers, and underscores');

            return 1;
        }

        $exampleVars = $this->parser->parse($examplePath);

        if (array_key_exists($key, $exampleVars)) {
            $this->error("Variable '{$key}' already exists in .env.example!");

            return 1;
        }

        $value = $this->ask('Enter variable value');

        $envContent = File::exists($envPath) ? File::get($envPath) : '';
        $envVars = $this->parser->parseContent($envContent);

        if (array_key_exists($key, $envVars)) {
            $overwrite = $this->confirm("Variable '{$key}' already exists in .env. Overwrite?", false);

            if (! $overwrite) {
                $this->info('Operation cancelled.');

                return 0;
            }
        }

        $exampleContent = File::get($examplePath);
        $newExampleContent = $this->parser->addVariable($exampleContent, $key, '****');
        File::put($examplePath, $newExampleContent);

        if (File::exists($envPath)) {
            if (array_key_exists($key, $envVars)) {
                $newEnvContent = $this->parser->replaceVariable($envContent, $key, $value);
                File::put($envPath, $newEnvContent);
            } else {
                $newEnvContent = $this->parser->addVariable($envContent, $key, $value);
                File::put($envPath, $newEnvContent);
            }
        } else {
            $newEnvContent = $this->parser->addVariable('', $key, $value);
            File::put($envPath, $newEnvContent);
        }

        $this->info("Variable '{$key}' added successfully!");
        $this->info('Added to .env.example with value: ****');
        $this->info("Added to .env with value: {$value}");

        return 0;
    }

    private function removeVariable(): int
    {
        $examplePath = base_path('.env.example');
        $envPath = base_path('.env');

        if (! File::exists($examplePath)) {
            $this->error('.env.example file not found!');

            return 1;
        }

        if (! File::exists($envPath)) {
            $this->error('.env file not found!');

            return 1;
        }

        $exampleVars = $this->parser->parse($examplePath);
        $envVars = $this->parser->parse($envPath);

        $allVariables = array_unique(array_merge(array_keys($exampleVars), array_keys($envVars)));

        if (empty($allVariables)) {
            $this->error('No variables found in either file!');

            return 1;
        }

        $key = $this->choice('Select variable to remove', $allVariables);

        $confirm = $this->confirm("Are you sure you want to remove '{$key}' from both files?", false);

        if (! $confirm) {
            $this->info('Operation cancelled.');

            return 0;
        }

        $exampleContent = File::get($examplePath);
        $envContent = File::get($envPath);

        $newExampleContent = $this->parser->removeVariable($exampleContent, $key);
        $newEnvContent = $this->parser->removeVariable($envContent, $key);

        File::put($examplePath, $newExampleContent);
        File::put($envPath, $newEnvContent);

        $this->info("Variable '{$key}' removed successfully from both files!");

        return 0;
    }
}
