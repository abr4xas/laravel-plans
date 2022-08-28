<?php

use Abr4xas\LaravelPlans\Models\Plan;
use Abr4xas\LaravelPlans\Tests\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->plan = Plan::factory()->create();
    $this->newPlan = Plan::factory()->create();
});

test('if user doesnt have any kind of subscription', function () {
    expect($this->user->subscriptions()->first())->toBeNull()
        ->and($this->user->activeSubscription())->toBeNull()
        ->and($this->user->lastActiveSubscription())->toBeNull()
        ->and($this->user->hasActiveSubscription())->toBeFalse();
});

test('it cant subscribe to a plan with invalid duration', function () {
    expect($this->user->subscribeTo($this->plan, 0))->toBeFalse()
        ->and($this->user->subscribeTo($this->plan, -1))->toBeFalse();
});

test('it cant subscribe to a plan with invalid date', function () {
    expect($this->user->subscribeToUntil($this->plan, Carbon::yesterday()))->toBeFalse()
        ->and($this->user->subscribeToUntil($this->plan, Carbon::yesterday()->toDateTimeString()))->toBeFalse()
        ->and($this->user->subscribeToUntil($this->plan, Carbon::yesterday()->toDateString()))->toBeFalse();
});

test('it can subscribe', function () {
    $subscription = $this->user->subscribeTo($this->plan, 15);
    sleep(1);

    $this->assertEquals($subscription->plan_id, $this->plan->id);
    $this->assertNotNull($this->user->subscriptions()->first());
    $this->assertEquals($this->user->subscriptions()->expired()->count(), 0);
    $this->assertEquals($this->user->subscriptions()->recurring()->count(), 1);
    $this->assertEquals($this->user->subscriptions()->cancelled()->count(), 0);
    $this->assertNotNull($this->user->activeSubscription());
    $this->assertNotNull($this->user->lastActiveSubscription());
    $this->assertTrue($this->user->hasActiveSubscription());
    $this->assertEquals($subscription->remainingDays(), 14);
});

test('it can subscribe until with a carbon instance', function () {
    $subscription = $this->user->subscribeToUntil($this->plan, Carbon::now()->addDays(15));
    sleep(1);

    $this->assertEquals($subscription->plan_id, $this->plan->id);
    $this->assertNotNull($this->user->subscriptions()->first());
    $this->assertEquals($this->user->subscriptions()->expired()->count(), 0);
    $this->assertEquals($this->user->subscriptions()->recurring()->count(), 1);
    $this->assertEquals($this->user->subscriptions()->cancelled()->count(), 0);
    $this->assertNotNull($this->user->activeSubscription());
    $this->assertNotNull($this->user->lastActiveSubscription());
    $this->assertTrue($this->user->hasActiveSubscription());
    $this->assertEquals($subscription->remainingDays(), 14);
});

test('it can subscribe until with date time string', function () {
    $subscription = $this->user->subscribeToUntil($this->plan, Carbon::now()->addDays(15)->toDateTimeString());
    sleep(1);

    $this->assertEquals($subscription->plan_id, $this->plan->id);
    $this->assertNotNull($this->user->subscriptions()->first());
    $this->assertNotNull($this->user->activeSubscription());
    $this->assertNotNull($this->user->lastActiveSubscription());
    $this->assertTrue($this->user->hasActiveSubscription());
    $this->assertEquals($subscription->remainingDays(), 14);
});

test('it can subscribe to until with a date string', function () {
    $subscription = $this->user->subscribeToUntil($this->plan, Carbon::now()->addDays(15)->toDateString());
    sleep(1);

    $this->assertEquals($subscription->plan_id, $this->plan->id);
    $this->assertNotNull($this->user->subscriptions()->first());
    $this->assertNotNull($this->user->activeSubscription());
    $this->assertNotNull($this->user->lastActiveSubscription());
    $this->assertTrue($this->user->hasActiveSubscription());
    $this->assertEquals($subscription->remainingDays(), 14);
});

test('it can upgrade with wrong duration', function () {
    $subscription = $this->user->subscribeTo($this->plan, 15);
    sleep(1);

    $this->assertFalse($this->user->upgradeCurrentPlanTo($this->newPlan, 0));
    $this->assertFalse($this->user->upgradeCurrentPlanTo($this->newPlan, -1));
});

test('it can upgrade with invalid date', function () {
    $subscription = $this->user->subscribeTo($this->plan, 15);
    sleep(1);

    $this->assertFalse($this->user->upgradeCurrentPlanToUntil($this->plan, Carbon::yesterday()));
    $this->assertFalse($this->user->upgradeCurrentPlanToUntil($this->plan, Carbon::yesterday()->toDateTimeString()));
    $this->assertFalse($this->user->upgradeCurrentPlanToUntil($this->plan, Carbon::yesterday()->toDateString()));
});

test('it can upgrade now', function () {
    $subscription = $this->user->subscribeTo($this->plan, 15);
    sleep(1);

    $subscription = $this->user->upgradeCurrentPlanTo($this->newPlan, 30, true);

    $this->assertEquals($subscription->plan_id, $this->newPlan->id);
    $this->assertEquals($subscription->remainingDays(), 44);
});

test('it can upgrade to another cycle', function () {
    $subscription = $this->user->subscribeTo($this->plan, 15);
    sleep(1);

    $this->user->upgradeCurrentPlanTo($this->newPlan, 30, false);

    $this->assertEquals($subscription->plan_id, $this->plan->id);
    $this->assertEquals($subscription->remainingDays(), 14);
    $this->assertEquals($this->user->subscriptions->count(), 2);
});

test('it can upgrade to now with carbon instance', function () {
    $subscription = $this->user->subscribeTo($this->plan, 15);
    sleep(1);

    $subscription = $this->user->upgradeCurrentPlanToUntil($this->newPlan, Carbon::now()->addDays(30), true);
    sleep(1);

    $this->assertEquals($subscription->plan_id, $this->newPlan->id);
    $this->assertEquals($subscription->remainingDays(), 29);
});

test('it can upgrade to another cycle with carbon instance', function () {
    $subscription = $this->user->subscribeTo($this->plan, 15);
    sleep(1);

    $this->user->upgradeCurrentPlanToUntil($this->newPlan, Carbon::now()->addDays(30), false);
    sleep(1);

    $this->assertEquals($subscription->plan_id, $this->plan->id);
    $this->assertEquals($subscription->remainingDays(), 14);
    $this->assertEquals($this->user->subscriptions->count(), 2);
});

test('it can upgrade to now with date time string', function () {
    $subscription = $this->user->subscribeTo($this->plan, 15);
    sleep(1);

    $subscription = $this->user->upgradeCurrentPlanToUntil($this->newPlan, Carbon::now()->addDays(30)->toDateTimeString(), true);
    sleep(1);

    $this->assertEquals($subscription->plan_id, $this->newPlan->id);
    $this->assertEquals($subscription->remainingDays(), 29);
});

test('it can upgrade to another cycle with date time string', function () {
    $subscription = $this->user->subscribeTo($this->plan, 15);
    sleep(1);

    $this->user->upgradeCurrentPlanToUntil($this->newPlan, Carbon::now()->addDays(30)->toDateTimeString(), false);
    sleep(1);

    $this->assertEquals($subscription->plan_id, $this->plan->id);
    $this->assertEquals($subscription->remainingDays(), 14);
    $this->assertEquals($this->user->subscriptions->count(), 2);
});

test('it can upgrade to now with date string', function () {
    $subscription = $this->user->subscribeTo($this->plan, 15);
    sleep(1);

    $subscription = $this->user->upgradeCurrentPlanToUntil($this->newPlan, Carbon::now()->addDays(30)->toDateString(), true);
    sleep(1);

    $this->assertEquals($subscription->plan_id, $this->newPlan->id);
    $this->assertEquals($subscription->remainingDays(), 29);
});

test('it can upgrade to another cycle with date string', function () {
    $subscription = $this->user->subscribeTo($this->plan, 15);
    sleep(1);

    $this->user->upgradeCurrentPlanToUntil($this->newPlan, Carbon::now()->addDays(30)->toDateString(), false);
    sleep(1);

    $this->assertEquals($subscription->plan_id, $this->plan->id);
    $this->assertEquals($subscription->remainingDays(), 14);
    $this->assertEquals($this->user->subscriptions->count(), 2);
});

test('it cant extend with wrong duration', function () {
    $subscription = $this->user->subscribeTo($this->plan, 15);
    sleep(1);

    $this->assertFalse($this->user->extendCurrentSubscriptionWith(-1));
    $this->assertEquals($subscription->plan_id, $this->plan->id);
    $this->assertEquals($subscription->remainingDays(), 14);
});

test('it can extent now', function () {
    $subscription = $this->user->subscribeTo($this->plan, 15);
    sleep(1);

    $subscription = $this->user->extendCurrentSubscriptionWith(30, true);
    sleep(1);

    $this->assertEquals($subscription->plan_id, $this->plan->id);
    $this->assertEquals($subscription->remainingDays(), 44);
});

test('it can extend to another cycle', function () {
    $subscription = $this->user->subscribeTo($this->plan, 15);
    sleep(1);

    $this->user->extendCurrentSubscriptionWith(30, false);
    sleep(1);

    $this->assertEquals($subscription->plan_id, $this->plan->id);
    $this->assertEquals($subscription->remainingDays(), 14);
    $this->assertEquals($this->user->subscriptions->count(), 2);
});

test('it can extend now with carbon instance', function () {
    $subscription = $this->user->subscribeTo($this->plan, 15);
    sleep(1);

    $subscription = $this->user->extendCurrentSubscriptionUntil(Carbon::now()->addDays(30), true);
    sleep(1);

    $this->assertEquals($subscription->plan_id, $this->plan->id);
    $this->assertEquals($subscription->remainingDays(), 29);
});

test('it can extend to another cycle with carbon instance', function () {
    $subscription = $this->user->subscribeTo($this->plan, 15);
    sleep(1);

    $this->user->extendCurrentSubscriptionUntil(Carbon::now()->addDays(30), false);
    sleep(1);

    $this->assertEquals($subscription->plan_id, $this->plan->id);
    $this->assertEquals($subscription->remainingDays(), 14);
    $this->assertEquals($this->user->subscriptions->count(), 2);
});

test('it can extend now with date time string', function () {
    $subscription = $this->user->subscribeTo($this->plan, 15);
    sleep(1);

    $subscription = $this->user->extendCurrentSubscriptionUntil(Carbon::now()->addDays(30)->toDateTimeString(), true);
    sleep(1);

    $this->assertEquals($subscription->plan_id, $this->plan->id);
    $this->assertEquals($subscription->remainingDays(), 29);
});

test('it can extend to another cycle with date time string', function () {
    $subscription = $this->user->subscribeTo($this->plan, 15);
    sleep(1);

    $this->user->extendCurrentSubscriptionUntil(Carbon::now()->addDays(30)->toDateTimeString(), false);
    sleep(1);

    $this->assertEquals($subscription->plan_id, $this->plan->id);
    $this->assertEquals($subscription->remainingDays(), 14);
    $this->assertEquals($this->user->subscriptions->count(), 2);
});

test('it can extend now with date string', function () {
    $subscription = $this->user->subscribeTo($this->plan, 15);
    sleep(1);

    $subscription = $this->user->extendCurrentSubscriptionUntil(Carbon::now()->addDays(30)->toDateString(), true);
    sleep(1);

    $this->assertEquals($subscription->plan_id, $this->plan->id);
    $this->assertEquals($subscription->remainingDays(), 29);
});

test('it can extend to another cycle with date string', function () {
    $subscription = $this->user->subscribeTo($this->plan, 15);
    sleep(1);

    $this->user->extendCurrentSubscriptionUntil(Carbon::now()->addDays(30)->toDateString(), false);
    sleep(1);

    $this->assertEquals($subscription->plan_id, $this->plan->id);
    $this->assertEquals($subscription->remainingDays(), 14);
    $this->assertEquals($this->user->subscriptions->count(), 2);
});

test('it can upgrade from user without active subscription', function () {
    $subscription = $this->user->upgradeCurrentPlanTo($this->newPlan, 15, true);
    sleep(1);

    $this->assertEquals($subscription->plan_id, $this->newPlan->id);
    $this->assertEquals($subscription->remainingDays(), 14);
});

test('it can upgrade to from user now', function () {
    $subscription = $this->user->subscribeTo($this->plan, 15);
    sleep(1);

    $subscription = $this->user->upgradeCurrentPlanTo($this->newPlan, 15, true);
    sleep(1);

    $this->assertEquals($subscription->plan_id, $this->newPlan->id);
    $this->assertEquals($subscription->remainingDays(), 29);
});

test('it can upgrade to from user to another cycle', function () {
    $subscription = $this->user->subscribeTo($this->plan, 15);
    sleep(1);

    $this->user->upgradeCurrentPlanTo($this->newPlan, 30, false);
    sleep(1);

    $this->assertEquals($subscription->plan_id, $this->plan->id);
    $this->assertEquals($subscription->remainingDays(), 14);
    $this->assertEquals($this->user->subscriptions->count(), 2);
});

test('it can extend from user without active subscription', function () {
    $subscription = $this->user->extendCurrentSubscriptionWith(15, true);
    sleep(1);

    $this->assertEquals($subscription->plan_id, $this->plan->id);
    $this->assertEquals($subscription->remainingDays(), 14);
});

test('it can extend from user now', function () {
    $subscription = $this->user->subscribeTo($this->plan, 15);
    sleep(1);

    $subscription = $this->user->extendCurrentSubscriptionWith(15, true);
    sleep(1);

    $this->assertEquals($subscription->plan_id, $this->plan->id);
    $this->assertEquals($subscription->remainingDays(), 29);
});

test('it can extend from user to another cycle', function () {
    $subscription = $this->user->subscribeTo($this->plan, 15);
    sleep(1);

    $this->user->extendCurrentSubscriptionWith(15, false);
    sleep(1);

    $this->assertEquals($subscription->plan_id, $this->plan->id);
    $this->assertEquals($subscription->remainingDays(), 14);
    $this->assertEquals($this->user->subscriptions->count(), 2);
});

test('it can cancel subscription', function () {
    $subscription = $this->user->subscribeTo($this->plan, 15);
    sleep(1);

    $this->assertEquals($subscription->plan_id, $this->plan->id);
    $this->assertEquals($subscription->remainingDays(), 14);

    $subscription = $this->user->cancelCurrentSubscription();
    sleep(1);

    $this->assertNotNull($subscription);
    $this->assertTrue($subscription->isCancelled());
    $this->assertTrue($subscription->isPendingCancellation());
    $this->assertFalse($this->user->cancelCurrentSubscription());
    $this->assertEquals($this->user->subscriptions()->cancelled()->count(), 1);
});

test('it can cancel subscription from user', function () {
    $subscription = $this->user->subscribeTo($this->plan, 15);
    sleep(1);

    $this->assertEquals($subscription->plan_id, $this->plan->id);
    $this->assertEquals($subscription->remainingDays(), 14);

    $subscription = $this->user->cancelCurrentSubscription();
    sleep(1);

    $this->assertNotNull($subscription);
    $this->assertTrue($subscription->isCancelled());
    $this->assertTrue($subscription->isPendingCancellation());
    $this->assertFalse($this->user->cancelCurrentSubscription());
});

test('it cant cancel subscription without subscription', function () {
    expect($this->user->cancelCurrentSubscription())
        ->toBeFalse();
});
