<?php

namespace Abr4xas\LaravelPlans\Events;

use Abr4xas\LaravelPlans\Models\Feature;
use Abr4xas\LaravelPlans\Models\Subscription;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FeatureUnconsumed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Subscription $subscription;

    public Feature $feature;

    public float $used;

    public float $remaining;

    /**
     * @param  Subscription  $subscription Subscription on which action was done.
     * @param  Feature  $feature The feature that was consumed.
     * @param  float  $used The amount used on this consumption.
     * @param  float  $remaining The amount remaining for this feature.
     * @return void
     */
    public function __construct(Subscription $subscription, Feature $feature, float $used, float $remaining)
    {
        $this->subscription = $subscription;
        $this->feature = $feature;
        $this->used = $used;
        $this->remaining = $remaining;
    }
}
