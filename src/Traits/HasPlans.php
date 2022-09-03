<?php

namespace Abr4xas\LaravelPlans\Traits;

use Abr4xas\LaravelPlans\Events\CancelSubscription;
use Abr4xas\LaravelPlans\Events\ExtendSubscription;
use Abr4xas\LaravelPlans\Events\ExtendSubscriptionUntil;
use Abr4xas\LaravelPlans\Events\NewSubscription;
use Abr4xas\LaravelPlans\Events\NewSubscriptionUntil;
use Abr4xas\LaravelPlans\Events\UpgradeSubscription;
use Abr4xas\LaravelPlans\Events\UpgradeSubscriptionUntil;
use Abr4xas\LaravelPlans\Models\Plan;
use Abr4xas\LaravelPlans\Models\Subscription;
use Carbon\Carbon;
use DateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasPlans
{
    /**
     * Get Subscriptions relationship.
     *
     * @return morphMany Relationship.
     */
    public function subscriptions(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'model');
    }

    /**
     * Return the current subscription relationship.
     *
     * @return morphMany Relationship.
     */
    public function currentSubscription(): MorphMany
    {
        return $this->subscriptions()
            ->where('starts_on', '<', Carbon::now())
            ->where('expires_on', '>', Carbon::now());
    }

    /**
     * Return the current active subscription.
     *
     * @return Subscription|null The PlanSubscription model instance.
     */
    public function activeSubscription(): ?Subscription
    {
        return $this->currentSubscription()->paid()->notCancelled()->first();
    }

    /**
     * Get the last active subscription.
     *
     * @return null|Subscription The PlanSubscription model instance.
     */
    public function lastActiveSubscription(): ?Subscription
    {
        if (! $this->hasSubscriptions()) {
            return null;
        }

        if ($this->hasActiveSubscription()) {
            return $this->activeSubscription();
        }

        return $this->subscriptions()->latest('starts_on')->paid()->notCancelled()->first();
    }

    /**
     * Get the last subscription.
     *
     * @return Model|MorphMany|null Either null or the last subscription.
     */
    public function lastSubscription(): Model|MorphMany|null
    {
        if (! $this->hasSubscriptions()) {
            return null;
        }

        if ($this->hasActiveSubscription()) {
            return $this->activeSubscription();
        }

        return $this->subscriptions()->latest('starts_on')->first();
    }

    /**
     * Get the last unpaid subscription, if any.
     *
     * @return Subscription
     */
    public function lastUnpaidSubscription(): Subscription
    {
        return $this->subscriptions()->latest('starts_on')
            ->notCancelled()
            ->unpaid()
            ->first();
    }

    /**
     * When a subscription is due, it means it was created, but not paid.
     * For example, on subscription, if your user wants to subscribe to another subscription and has a due (unpaid) one, it will
     * check for the last due, will cancel it, and will re-subscribe to it.
     *
     * @return null|Subscription Null or a Plan Subscription instance.
     */
    public function lastDueSubscription(): ?Subscription
    {
        if (! $this->hasSubscriptions() || $this->hasActiveSubscription()) {
            return null;
        }

        $lastActiveSubscription = $this->lastActiveSubscription();

        if (! $lastActiveSubscription) {
            return $this->lastUnpaidSubscription();
        }

        $lastSubscription = $this->lastSubscription();

        if ($lastActiveSubscription->is($lastSubscription)) {
            return null;
        }

        return $this->lastUnpaidSubscription();
    }

    /**
     * Check if the model has subscriptions.
     *
     * @return bool whether the binded model has subscriptions or not.
     */
    public function hasSubscriptions(): bool
    {
        return (bool) ($this->subscriptions()->count() > 0);
    }

    /**
     * Check if the model has an active subscription right now.
     *
     * @return bool whether the binded model has an active subscription or not.
     */
    public function hasActiveSubscription(): bool
    {
        return (bool) $this->activeSubscription();
    }

    /**
     * Check if the mode has a due, unpaid subscription.
     *
     * @return bool
     */
    public function hasDueSubscription(): bool
    {
        return (bool) $this->lastDueSubscription();
    }

    /**
     * Subscribe the binded model to a plan. Returns false if it has an active subscription already.
     *
     * @param  Plan  $plan The Plan model instance.
     * @param  int  $duration The duration, in days, for the subscription.
     * @param  bool  $isRecurring whether the subscription should auto-renew every $duration days.
     * @return false|Model The PlanSubscription model instance.
     */
    public function subscribeTo(Plan $plan, int $duration = 30, bool $isRecurring = true): Model|bool
    {
        $subscriptionModel = Subscription::class;

        if ($duration < 1 || $this->hasActiveSubscription()) {
            return false;
        }

        if ($this->hasDueSubscription()) {
            $this->lastDueSubscription()->delete();
        }

        $subscription = $this->subscriptions()->save(new $subscriptionModel([
            'plan_id' => $plan->id,
            'starts_on' => Carbon::now()->subSeconds(1),
            'expires_on' => Carbon::now()->addDays($duration),
            'cancelled_on' => null,
            'payment_method' => null,
            'active' => true,
            'charging_price' => $plan->price,
            'charging_currency' => $plan->currency,
            'is_recurring' => $isRecurring,
            'recurring_each_days' => $duration,
        ]));

        event(new NewSubscription($this, $subscription));

        return $subscription;
    }

    /**
     * Subscribe the binded model to a plan. Returns false if it has an active subscription already.
     *
     * @param  Plan  $plan The Plan model instance.
     * @param  DateTime|string  $date The date (either DateTime, date or Carbon instance) until the subscription will be extended until.
     * @param  bool  $isRecurring whether the subscription should auto-renew. The renewal period (in days) is the difference between now and the set date.
     * @return false|Model The PlanSubscription model instance.
     */
    public function subscribeToUntil(Plan $plan, DateTime|string $date, bool $isRecurring = true): Model|bool
    {
        $date = Carbon::parse($date);

        if ($date->lessThanOrEqualTo(Carbon::now()) || $this->hasActiveSubscription()) {
            return false;
        }

        if ($this->hasDueSubscription()) {
            $this->lastDueSubscription()->delete();
        }

        $subscription = $this->subscriptions()->save(new Subscription([
            'plan_id' => $plan->id,
            'starts_on' => Carbon::now()->subSeconds(1),
            'expires_on' => $date,
            'cancelled_on' => null,
            'payment_method' => null,
            'active' => true,
            'charging_price' => $plan->price,
            'charging_currency' => $plan->currency,
            'is_recurring' => $isRecurring,
            'recurring_each_days' => Carbon::now()->subSeconds(1)->diffInDays($date),
        ]));

        event(new NewSubscriptionUntil($this, $subscription, $date));

        return $subscription;
    }

    /**
     * Upgrade the binded model's plan. If it is the same plan, it just extends it.
     *
     * @param  Plan  $newPlan The new Plan model instance.
     * @param  int  $duration The duration, in days, for the new subscription.
     * @param  bool  $startFromNow whether the subscription will start from now, extending the current plan, or a new subscription will be created to extend the current one.
     * @param  bool  $isRecurring whether the subscription should auto-renew. The renewal period (in days) is the difference between now and the set date.
     * @return bool|Model The PlanSubscription model instance with the new plan or the current one, extended.
     */
    public function upgradeCurrentPlanTo(Plan $newPlan, int $duration = 30, bool $startFromNow = true, bool $isRecurring = true): Model|bool
    {
        if (! $this->hasActiveSubscription()) {
            return $this->subscribeTo($newPlan, $duration, $isRecurring);
        }

        if ($duration < 1) {
            return false;
        }

        $activeSubscription = $this->activeSubscription();
        $activeSubscription->load(['plan']);

        $subscription = $this->extendCurrentSubscriptionWith($duration, $startFromNow, $isRecurring);
        $oldPlan = $activeSubscription->plan;

        if ($subscription->plan_id != $newPlan->id) {
            $subscription->update([
                'plan_id' => $newPlan->id,
            ]);
        }

        event(new UpgradeSubscription($this, $subscription, $startFromNow, $oldPlan, $newPlan));

        return $subscription;
    }

    /**
     * Upgrade the binded model's plan. If it is the same plan, it just extends it.
     *
     * @param Plan $newPlan The new Plan model instance.
     * @param DateTime|string $date The date (either DateTime, date or Carbon instance) until the subscription will be extended until.
     * @param  bool  $startFromNow whether the subscription will start from now, extending the current plan, or a new subscription will be created to extend the current one.
     * @param  bool  $isRecurring whether the subscription should auto-renew. The renewal period (in days) is the difference between now and the set date.
     * @return false|Model|Subscription The PlanSubscription model instance with the new plan or the current one, extended.
     */
    public function upgradeCurrentPlanToUntil(Plan $newPlan, DateTime|string $date, bool $startFromNow = true, bool $isRecurring = true): Model|bool|Subscription
    {
        if (! $this->hasActiveSubscription()) {
            return $this->subscribeToUntil($newPlan, $date, $isRecurring);
        }

        $activeSubscription = $this->activeSubscription();
        $activeSubscription->load(['plan']);

        $subscription = $this->extendCurrentSubscriptionUntil($date, $startFromNow, $isRecurring);
        $oldPlan = $activeSubscription->plan;

        $date = Carbon::parse($date);

        if ($startFromNow) {
            if ($date->lessThanOrEqualTo(Carbon::now())) {
                return false;
            }
        }

        if (Carbon::parse($subscription->expires_on)->greaterThan($date)) {
            return false;
        }

        if ($subscription->plan_id != $newPlan->id) {
            $subscription->update([
                'plan_id' => $newPlan->id,
            ]);
        }

        event(new UpgradeSubscriptionUntil($this, $subscription, $date, $startFromNow, $oldPlan, $newPlan));

        return $subscription;
    }

    /**
     * Extend the current subscription with an amount of days.
     *
     * @param  int  $duration The duration, in days, for the extension.
     * @param  bool  $startFromNow whether the subscription will be extended from now, extending to the current plan, or a new subscription will be created to extend the current one.
     * @param  bool  $isRecurring whether the subscription should auto-renew. The renewal period (in days) equivalent with $duration.
     * @return bool|Subscription The PlanSubscription model instance of the extended subscription.
     */
    public function extendCurrentSubscriptionWith(int $duration = 30, bool $startFromNow = true, bool $isRecurring = true): bool|Subscription
    {
        if (! $this->hasActiveSubscription()) {
            if ($this->hasSubscriptions()) {
                $lastActiveSubscription = $this->lastActiveSubscription();
                $lastActiveSubscription->load(['plan']);

                return $this->subscribeTo($lastActiveSubscription->plan, $duration, $isRecurring);
            }

            return $this->subscribeTo(Plan::first(), $duration, $isRecurring);
        }

        if ($duration < 1) {
            return false;
        }

        $activeSubscription = $this->activeSubscription();

        if ($startFromNow) {
            $activeSubscription->update([
                'expires_on' => Carbon::parse($activeSubscription->expires_on)->addDays($duration),
            ]);

            event(new ExtendSubscription($this, $activeSubscription, $startFromNow, null));

            return $activeSubscription;
        }

        $subscription = $this->subscriptions()->save(new Subscription([
            'plan_id' => $activeSubscription->plan_id,
            'starts_on' => Carbon::parse($activeSubscription->expires_on),
            'expires_on' => Carbon::parse($activeSubscription->expires_on)->addDays($duration),
            'cancelled_on' => null,
            'payment_method' => null,
            'is_recurring' => $isRecurring,
            'recurring_each_days' => $duration,
        ]));

        event(new ExtendSubscription($this, $activeSubscription, $startFromNow, $subscription));

        return $subscription;
    }

    /**
     * Extend the subscription until a certain date.
     *
     * @param  DateTime|string  $date The date (either DateTime, date or Carbon instance) until the subscription will be extended until.
     * @param  bool  $startFromNow whether the subscription will be extended from now, extending to the current plan, or a new subscription will be created to extend the current one.
     * @param  bool  $isRecurring whether the subscription should auto-renew. The renewal period (in days) is the difference between now and the set date.
     * @return false|Subscription The Subscription model instance of the extended subscription.
     */
    public function extendCurrentSubscriptionUntil(DateTime|string $date, bool $startFromNow = true, bool $isRecurring = true): bool|Subscription
    {
        if (! $this->hasActiveSubscription()) {
            if ($this->hasSubscriptions()) {
                $lastActiveSubscription = $this->lastActiveSubscription();
                $lastActiveSubscription->load(['plan']);

                return $this->subscribeToUntil($lastActiveSubscription->plan, $date, $isRecurring);
            }

            return $this->subscribeToUntil(Plan::first(), $date, $isRecurring);
        }

        $date = Carbon::parse($date);
        $activeSubscription = $this->activeSubscription();

        if ($startFromNow) {
            if ($date->lessThanOrEqualTo(Carbon::now())) {
                return false;
            }

            $activeSubscription->update([
                'expires_on' => $date,
            ]);

            event(new ExtendSubscriptionUntil($this, $activeSubscription, $date, $startFromNow, null));

            return $activeSubscription;
        }

        if (Carbon::parse($activeSubscription->expires_on)->greaterThan($date)) {
            return false;
        }

        $subscription = $this->subscriptions()->save(new Subscription([
            'plan_id' => $activeSubscription->plan_id,
            'starts_on' => Carbon::parse($activeSubscription->expires_on),
            'expires_on' => $date,
            'cancelled_on' => null,
            'payment_method' => null,
            'is_recurring' => $isRecurring,
            'recurring_each_days' => Carbon::now()->subSeconds(1)->diffInDays($date),
        ]));

        event(new ExtendSubscriptionUntil($this, $activeSubscription, $date, $startFromNow, $subscription));

        return $subscription;
    }

    /**
     * Cancel the current subscription.
     *
     * @return Subscription|false whether the subscription was cancelled or not.
     */
    public function cancelCurrentSubscription(): bool|Subscription
    {
        if (! $this->hasActiveSubscription()) {
            return false;
        }

        $activeSubscription = $this->activeSubscription();

        if ($activeSubscription->isCancelled() || $activeSubscription->isPendingCancellation()) {
            return false;
        }

        $activeSubscription->update([
            'cancelled_on' => Carbon::now(),
            'is_recurring' => false,
        ]);

        event(new CancelSubscription($this, $activeSubscription));

        return $activeSubscription;
    }

    /**
     * Renew the subscription, if needed, and create a new charge
     * if the last active subscription was using Stripe and was paid.
     *
     * @return false|Subscription
     */
    public function renewSubscription(): Subscription|bool
    {
        if (! $this->hasSubscriptions()) {
            return false;
        }

        if ($this->hasActiveSubscription()) {
            return false;
        }

        $lastActiveSubscription = $this->lastActiveSubscription();

        if (! $lastActiveSubscription) {
            return false;
        }

        if (! $lastActiveSubscription->is_recurring || $lastActiveSubscription->isCancelled()) {
            return false;
        }

        $lastActiveSubscription->load(['plan']);
        $plan = $lastActiveSubscription->plan;
        $recurringEachDays = $lastActiveSubscription->recurring_each_days;

        if ($lastActiveSubscription->payment_method) {
            if (! $lastActiveSubscription->active) {
                return false;
            }
        }

        return $this->subscribeTo($plan, $recurringEachDays);
    }
}
