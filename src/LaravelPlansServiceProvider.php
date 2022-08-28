<?php

namespace Abr4xas\LaravelPlans;

use Abr4xas\LaravelPlans\Commands\LaravelPlansCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelPlansServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-plans')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigrations([
                'create_plans_table.php.stub',
                'create_features_table.php.stub',
                'create_subscriptions_table.php.stub',
                'create_plan_subscription_usages_table.php.stub',
            ])
            ->hasCommand(LaravelPlansCommand::class);
    }
}
