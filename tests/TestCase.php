<?php

namespace Gerenuk\Nudge\Tests;

use Gerenuk\Nudge\NudgeServiceProvider;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    protected function getPackageProviders($app)
    {
        return [
            NudgeServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('nudge.url', 'https://nudge.test/');
        config()->set('nudge.token', 'gmk_test');
        config()->set('nudge.queue.connection', 'redis');
        config()->set('nudge.queue.name', 'marketing');
    }
}
