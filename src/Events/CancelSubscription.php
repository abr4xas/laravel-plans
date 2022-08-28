<?php

namespace Abr4xas\LaravelPlans\Events;

use Abr4xas\LaravelPlans\Models\Subscription;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CancelSubscription
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Model $model;

    public Subscription $subscription;

    /**
     * @param  Model  $model The model on which the action was done.
     * @param  Subscription  $subscription Subscription that was cancelled.
     * @return void
     */
    public function __construct(Model $model, Subscription $subscription)
    {
        $this->model = $model;
        $this->subscription = $subscription;
    }
}
