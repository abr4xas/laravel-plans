## Laravel Plans
Laravel Plans is a package for SaaS apps that need management over plans, features, subscriptions, events for plans or limited, countable features.

### Laravel Cashier

---
While Laravel Cashier does this job really well, there are some features that can be useful for SaaS apps:

* **Countable, limited features** - If you plan to limit the amount of resources a subscriber can have and track the usage, this package does that for you.
* **Event-driven by nature** - you can listen for events. What if you can give 3 free days to the next subscription if your users pay their invoice in time?

### Installation

---

Install the package:
```bash
$ composer require abr4xas/laravel-plans
```

If your Laravel version does not support package discovery, add this line in the `providers` array in your `config/app.php` file:
```php
Abr4xas\Plans\PlansServiceProvider::class,
```

Publish the config file & migration files:
```bash
$ php artisan vendor:publish --provider=Abr4xas\Plans\PlansServiceProvider
```

Migrate the database:
```bash
$ php artisan migrate
```

Add the `HasPlans` trait to your Eloquent model:
```php
use Abr4xas\Plans\Traits\HasPlans;

class User extends Model {
    use HasPlans;
    ...
}
```

### Creating plans

---

The basic unit of the subscription-like system is a plan. You can create it using `Abr4xas\Plans\Models\Plan` or your model, if you have implemented your own.

```php
$plan = Plan::create([
    'name' => 'Enterprise',
    'description' => 'The biggest plans of all.',
    'duration' => 30, // in days
]);
```

### Features

---

Each plan has features. They can be either countable, and those are limited or unlimited, or there just to store the information, such a specific permission.

Marking a feature type can be done using:
* `feature`, is a single string, that do not needs counting. For example, you can store permissions.
* `limit`, is a number. For this kind of feature, the `limit` attribute will be filled. It is meant to measure how many of that feature the user has consumed, from this subscription. For example, you can count how many build minutes this user has consumed during the month (or during the Cycle, which is 30 days in this example)

**Note: For unlimited feature, the `limit` field will be set to any negative value.**

To attach features to your plan, you can use the relationship `features()` and pass as many `Abr4xas\Plans\Models\Feature`instances as you need:

```php
$plan->features()->saveMany([
    new Feature([
        'name' => 'Vault access',
        'code' => 'vault.access',
        'description' => 'Offering access to the vault.',
        'type' => 'feature',
    ]),
    new Feature([
        'name' => 'Build minutes',
        'code' => 'build.minutes',
        'description' => 'Build minutes used for CI/CD.',
        'type' => 'limit',
        'limit' => 2000,
    ]),
    new Feature([
        'name' => 'Users amount',
        'code' => 'users.amount',
        'description' => 'The maximum amount of users that can use the app at the same time.',
        'type' => 'limit',
        'limit' => -1, // or any negative value
    ]),
    ...
]);
```

Later, you can retrieve the permissions directly from the subscription:
```php
$subscription->features()->get(); // All features
$subscription->features()->code($codeId)->first(); // Feature with a specific code.
$subscription->features()->limited()->get(); // Only countable/unlimited features.
$subscription->features()->feature()->get(); // Uncountable, permission-like features.
```

### Subscribing to plans

---

Your users can be subscribed to plans for a certain amount of days or until a certain date.

```php
$subscription = $user->subscribeTo($plan, 30); // 30 days
$subscription->remainingDays(); // 29 (29 days, 23 hours, ...)
```

By default, the plan is marked as `recurring`, so it's eligible to be extended after it expires, if you plan to do so like it's explained in the **Recurrency** section below.

If you don't want a recurrent subscription, you can pass `false` as a third argument:

```php
$subscription = $user->subscribeTo($plan, 30, false); // 30 days, non-recurrent
```

If you plan to subscribe your users until a certain date, you can pass strngs containing a date, a datetime or a Carbon instance.

If your subscription is recurrent, the amount of days for a recurrency cycle is the difference between the expiring date and the current date.

```php
$user->subscribeToUntil($plan, '2018-12-21');
$user->subscribeToUntil($plan, '2018-12-21 16:54:11');
$user->subscribeToUntil($plan, Carbon::create(2018, 12, 21, 16, 54, 11));
$user->subscribeToUntil($plan, '2018-12-21', false); // no recurrency
```

**Note: If the user is already subscribed, the `subscribeTo()` will return false. To avoid this, upgrade or extend the subscription.**

### Upgrading subscription

---

Upgrading the current subscription's plan can be done in two ways: it either extends the current subscription with the amount of days passed or creates another one, in extension to this current one.

Either way, you have to pass a boolean as the third parameter. By default, it extends the current subscription.

```php
// The current subscription got longer with 60 days.
$currentSubscription = $user->upgradeCurrentPlanTo($anotherPlan, 60, true);

// A new subscription, with 60 days valability, starting when the current one ends.
$newSubscription = $user->upgradeCurrentPlanTo($anotherPlan, 60, false);
```

Just like the subscribe methods, upgrading also support dates as a third parameter if you plan to create a new subscription at the end of the current one.

```php
$user->upgradeCurrentPlanToUntil($anotherPlan, '2018-12-21', false);
$user->upgradeCurrentPlanToUntil($anotherPlan, '2018-12-21 16:54:11', false);
$user->upgradeCurrentPlanToUntil($anotherPlan, Carbon::create(2018, 12, 21, 16, 54, 11), false);
```

Passing a fourth parameter is available, if your third parameter is `false`, and you should pass it if you'd like to mark the new subscription as recurring.

```php
// Creates a new subscription that starts at the end of the current one, for 30 days and recurrent.
$newSubscription = $user->upgradeCurrentPlanTo($anotherPlan, 30, false, true);
```

### Extending current subscription

---

Upgrading uses the extension methods, so it uses the same arguments, but you do not pass as the first argument a Plan model:

```php
// The current subscription got extended with 60 days.
$currentSubscription = $user->extendCurrentSubscriptionWith(60, true);

// A new subscription, which starts at the end of the current one.
$newSubscription = $user->extendCurrentSubscriptionWith(60, false);

// A new subscription, which starts at the end of the current one and is recurring.
$newSubscription = $user->extendCurrentSubscriptionWith(60, false, true);
```

Extending also works with dates:

```php
$user->extendCurrentSubscriptionUntil('2018-12-21');
```

### Cancelling subscriptions

---

You can cancel subscriptions. If a subscription is not finished yet (it is not expired), it will be marked as `pending cancellation`. It will be fully cancelled when the expiration dates passes the current time and is still cancelled.
```php
// Returns false if there is not an active subscription.
$user->cancelCurrentSubscription();
$lastActiveSubscription = $user->lastActiveSubscription();

$lastActiveSubscription->isCancelled(); // true
$lastActiveSubscription->isPendingCancellation(); // true
$lastActiveSubscription->isActive(); // false

$lastActiveSubscription->hasStarted();
$lastActiveSubscription->hasExpired();
```

### Consuming countable features

---

To consume the `limit` type feature, you have to call the `consumeFeature()` method within a subscription instance.

To retrieve a subscription instance, you can call `activeSubscription()` method within the user that implements the trait. As a pre-check, don't forget to call `hasActiveSubscription()` from the user instance to make sure it is subscribed to it.

```php
if ($user->hasActiveSubscription()) {
    $subscription = $user->activeSubscription();
    $subscription->consumeFeature('build.minutes', 10);

    $subscription->getUsageOf('build.minutes'); // 10
    $subscription->getRemainingOf('build.minutes'); // 1990
}
```

The `consumeFeature()` method will return:
* `false` if the feature does not exist, the feature is not a `limit` or the amount is exceeding the current feature allowance
* `true` if the consumption was done successfully

```php
// Note: The remaining of build.minutes is now 1990

$subscription->consumeFeature('build.minutes', 1991); // false
$subscription->consumeFeature('build.hours', 1); // false
$subscription->consumeFeature('build.minutes', 30); // true

$subscription->getUsageOf('build.minutes'); // 40
$subscription->getRemainingOf('build.minutes'); // 1960
```

If `consumeFeature()` meets an unlimited feature, it will consume it and it will also track usage just like a normal record in the database, but will never return false. The remaining will always be `-1` for unlimited features.

The revering method for `consumeFeature()` method is `unconsumeFeature()`. This works just the same, but in the reverse:

```php
// Note: The remaining of build.minutes is 1960

$subscription->consumeFeature('build.minutes', 60); // true

$subscription->getUsageOf('build.minutes'); // 100
$subscription->getRemainingOf('build.minutes'); // 1900

$subscription->unconsumeFeature('build.minutes', 100); // true
$subscription->unconsumeFeature('build.hours', 1); // false

$subscription->getUsageOf('build.minutes'); // 0
$subscription->getRemainingOf('build.minutes'); // 2000
```

Using the `unconsumeFeature()` method on unlimited features will also reduce usage, but it will never reach negative values.

### Events

---

When using subscription plans, you want to listen for events to automatically run code that might do changes for your app.

Events are easy to use. If you are not familiar, you can check [Laravel's Official Documentation on Events](https://laravel.com/docs/9.x/events).

All you have to do is to implement the following Events in your `EventServiceProvider.php` file. Each event will have it's own members than can be accessed through the `$event` variable within the `handle()` method in your listener.

```php
$listen = [
    ...
    \Abr4xas\Plans\Events\CancelSubscription::class => [
        // $event->model = The model that cancelled the subscription.
        // $event->subscription = The subscription that was cancelled.
    ],
    \Abr4xas\Plans\Events\NewSubscription::class => [
        // $event->model = The model that was subscribed.
        // $event->subscription = The subscription that was created.
    ],
     \Abr4xas\Plans\Events\NewSubscriptionUntil::class => [
        // $event->model = The model that was subscribed.
        // $event->subscription = The subscription that was created.
    ],
    \Abr4xas\Plans\Events\ExtendSubscription::class => [
        // $event->model = The model that extended the subscription.
        // $event->subscription = The subscription that was extended.
        // $event->startFromNow = If the subscription is exteded now or is created a new subscription, in the future.
        // $event->newSubscription = If the startFromNow is false, here will be sent the new subscription that starts after the current one ends.
    ],
    \Abr4xas\Plans\Events\ExtendSubscriptionUntil::class => [
        // $event->model = The model that extended the subscription.
        // $event->subscription = The subscription that was extended.
        // $event->expiresOn = The Carbon instance of the date when the subscription will expire.
        // $event->startFromNow = If the subscription is exteded now or is created a new subscription, in the future.
        // $event->newSubscription = If the startFromNow is false, here will be sent the new subscription that starts after the current one ends.
    ],
    \Abr4xas\Plans\Events\UpgradeSubscription::class => [
        // $event->model = The model that upgraded the subscription.
        // $event->subscription = The current subscription.
        // $event->startFromNow = If the subscription is upgraded now or is created a new subscription, in the future.
        // $event->oldPlan = Here lies the current (which is now old) plan.
        // $event->newPlan = Here lies the new plan. If it's the same plan, it will match with the $event->oldPlan
    ],
    \Abr4xas\Plans\Events\UpgradeSubscriptionUntil::class => [
        // $event->model = The model that upgraded the subscription.
        // $event->subscription = The current subscription.
        // $event->expiresOn = The Carbon instance of the date when the subscription will expire.
        // $event->startFromNow = If the subscription is upgraded now or is created a new subscription, in the future.
        // $event->oldPlan = Here lies the current (which is now old) plan.
        // $event->newPlan = Here lies the new plan. If it's the same plan, it will match with the $event->oldPlan
    ],
    \Abr4xas\Plans\Events\FeatureConsumed::class => [
        // $event->subscription = The current subscription.
        // $event->feature = The feature that was used.
        // $event->used = The amount used.
        // $event->remaining = The total amount remaining. If the feature is unlimited, will return -1
    ],
     \Abr4xas\Plans\Events\FeatureUnconsumed::class => [
        // $event->subscription = The current subscription.
        // $event->feature = The feature that was used.
        // $event->used = The amount reverted.
        // $event->remaining = The total amount remaining. If the feature is unlimited, will return -1
    ],
];
```

### Testing

```bash
composer test
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

### Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

### Credits

- [Angel](https://github.com/abr4xas)
- **Georgescu Alexandru** *Initial work*
- [Musa Kurt](https://github.com/whtht)
- [All Contributors](../../contributors)

### License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
