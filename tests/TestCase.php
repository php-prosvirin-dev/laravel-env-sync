<?php

namespace Prosvirin\EnvSync\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Prosvirin\EnvSync\EnvSyncServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return \class-string[]
     */
    protected function getPackageProviders($app): array
    {
        return [
            EnvSyncServiceProvider::class,
        ];
    }
}
