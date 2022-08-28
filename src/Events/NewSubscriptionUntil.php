<?php

namespace Abr4xas\LaravelPlans\Events;

use Abr4xas\LaravelPlans\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewSubscriptionUntil
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Model $model;

    public Subscription $subscription;

    public Carbon $expiresOn;

    /**
     * @param  Model  $model The model that subscribed.
     * @param  Subscription  $subscription Subscription the model has subscribed to.
     * @param  Carbon  $expiresOn The date when the subscription expires.
     * @return void
     */
    public function __construct(Model $model, Subscription $subscription, Carbon $expiresOn)
    {
        $this->model = $model;
        $this->subscription = $subscription;
        $this->expiresOn = $expiresOn;
    }
}
