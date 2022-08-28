<?php

namespace Abr4xas\LaravelPlans\Tests;

use Abr4xas\LaravelPlans\LaravelPlansServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Abr4xas\\LaravelPlans\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelPlansServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        $create_users_table = include __DIR__.'/database/migrations/create_users_table.php.stub';
        $createPlans = include __DIR__.'/../database/migrations/create_plans_table.php.stub';
        $createFeatures = include __DIR__.'/../database/migrations/create_features_table.php.stub';
        $createSubscriptions = include __DIR__.'/../database/migrations/create_subscriptions_table.php.stub';
        $createPlanSubscriptionUsages = include __DIR__.'/../database/migrations/create_plan_subscription_usages_table.php.stub';

        $create_users_table->up();
        $createPlans->up();
        $createFeatures->up();
        $createSubscriptions->up();
        $createPlanSubscriptionUsages->up();
    }
}
