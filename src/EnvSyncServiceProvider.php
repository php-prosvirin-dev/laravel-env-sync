<?php

namespace Prosvirin\EnvSync;

use Illuminate\Support\ServiceProvider;
use Prosvirin\EnvSync\Commands\EnvSyncCommand;

class EnvSyncServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                EnvSyncCommand::class,
            ]);
        }
    }
}
