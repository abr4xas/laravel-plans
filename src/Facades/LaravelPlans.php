<?php

namespace Abr4xas\LaravelPlans\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Abr4xas\LaravelPlans\LaravelPlans
 */
class LaravelPlans extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Abr4xas\LaravelPlans\LaravelPlans::class;
    }
}
