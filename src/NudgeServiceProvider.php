<?php

namespace Gerenuk\Nudge;

use Gerenuk\Nudge\Commands\CheckCommand;
use Gerenuk\Nudge\Contracts\Transport;
use Gerenuk\Nudge\Http\HttpTransport;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class NudgeServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('nudge-for-laravel')
            ->hasConfigFile('nudge')
            ->hasCommand(CheckCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->bind(Transport::class, fn (Application $app): Transport => new HttpTransport(
            $app->make(Factory::class),
            $app->make(Repository::class),
        ));

        $this->app->bind(Nudge::class, fn (Application $app): Nudge => new Nudge(
            $app->make(Transport::class),
            $app->make(Repository::class),
        ));
    }
}
