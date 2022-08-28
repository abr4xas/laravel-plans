<?php

namespace Abr4xas\LaravelPlans\Events;

use Abr4xas\LaravelPlans\Models\Subscription;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewSubscription
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Model $model;

    public Subscription $subscription;

    /**
     * @param  Model  $model The model that subscribed.
     * @param  Subscription  $subscription Subscription the model has subscribed to.
     * @return void
     */
    public function __construct(Model $model, Subscription $subscription)
    {
        $this->model = $model;
        $this->subscription = $subscription;
    }
}
