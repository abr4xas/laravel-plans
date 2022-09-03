<?php

namespace Abr4xas\LaravelPlans\Events;

use Abr4xas\LaravelPlans\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExtendSubscriptionUntil
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Model $model;

    public Subscription $subscription;

    public Carbon $expiresOn;

    public bool $startFromNow;

    public ?Subscription $newSubscription;

    /**
     * @param  Model  $model The model on which the action was done.
     * @param  Subscription  $subscription Subscription that was extended.
     * @param  Carbon  $expiresOn The date when the subscription expires.
     * @param  bool  $startFromNow Wether the current subscription is extended or is created at the next cycle.
     * @param  Subscription|null  $newSubscription Null if $startFromNow is true; The new subscription created in extension.
     * @return void
     */
    public function __construct(Model $model, Subscription $subscription, Carbon $expiresOn, bool $startFromNow, ?Subscription $newSubscription)
    {
        $this->model = $model;
        $this->subscription = $subscription;
        $this->expiresOn = $expiresOn;
        $this->startFromNow = $startFromNow;
        $this->newSubscription = $newSubscription;
    }
}
