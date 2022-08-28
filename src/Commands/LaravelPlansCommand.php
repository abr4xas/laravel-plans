<?php

namespace Abr4xas\LaravelPlans\Commands;

use Illuminate\Console\Command;

class LaravelPlansCommand extends Command
{
    public $signature = 'laravel-plans';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
