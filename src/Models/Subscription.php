<?php

namespace Abr4xas\LaravelPlans\Models;

use Abr4xas\LaravelPlans\Events\FeatureConsumed;
use Abr4xas\LaravelPlans\Events\FeatureUnconsumed;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'model_id',
        'model_type',
        'payment_method',
        'active',
        'charging_price',
        'charging_currency',
        'is_recurring',
        'recurring_each_days',
        'starts_on',
        'expires_on',
        'cancelled_on',
    ];

    protected $dates = [
        'starts_on',
        'expires_on',
        'cancelled_on',
    ];

    protected $casts = [
        'active' => 'boolean',
        'is_recurring' => 'boolean',
    ];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function features()
    {
        return $this->plan()->first()->features();
    }

    public function usages(): HasMany
    {
        return $this->hasMany(PlanSubscriptionUsage::class);
    }

    public function scopePaid($query)
    {
        return $query->where('active', true);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('active', false);
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_on', '<', Carbon::now()->toDateTimeString());
    }

    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true);
    }

    public function scopeCancelled($query)
    {
        return $query->whereNotNull('cancelled_on');
    }

    public function scopeNotCancelled($query)
    {
        return $query->whereNull('cancelled_on');
    }

    public function scopeStripe($query)
    {
        return $query->where('payment_method', 'stripe');
    }

    /**
     * Checks if the current subscription has started.
     *
     * @return bool
     */
    public function hasStarted(): bool
    {
        return (bool) Carbon::now()->greaterThanOrEqualTo(Carbon::parse($this->starts_on));
    }

    /**
     * Checks if the current subscription has expired.
     *
     * @return bool
     */
    public function hasExpired(): bool
    {
        return (bool) Carbon::now()->greaterThan(Carbon::parse($this->expires_on));
    }

    /**
     * Checks if the current subscription is active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return (bool) ($this->hasStarted() && ! $this->hasExpired());
    }

    /**
     * Get the remaining days in this subscription.
     *
     * @return int
     */
    public function remainingDays(): int
    {
        if ($this->hasExpired()) {
            return (int) 0;
        }

        return (int) Carbon::now()->diffInDays(Carbon::parse($this->expires_on));
    }

    /**
     * Checks if the current subscription is cancelled (expiration date is in the past & the subscription is cancelled).
     *
     * @return bool
     */
    public function isCancelled(): bool
    {
        return (bool) $this->cancelled_on != null;
    }

    /**
     * Checks if the current subscription is pending cancellation.
     *
     * @return bool
     */
    public function isPendingCancellation(): bool
    {
        return (bool) ($this->isCancelled() && $this->isActive());
    }

    /**
     * Cancel this subscription.
     *
     * @return self $this
     */
    public function cancel(): static
    {
        $this->update([
            'cancelled_on' => Carbon::now(),
        ]);

        return $this;
    }

    /**
     * Consume a feature, if it is 'limit' type.
     *
     * @param  string  $featureCode The feature code. This feature has to be 'limit' type.
     * @param  float  $amount The amount consumed.
     * @return bool Wether the feature was consumed successfully or not.
     */
    public function consumeFeature(string $featureCode, float $amount): bool
    {
        $usageModel = PlanSubscriptionUsage::class;

        $feature = $this->features()->code($featureCode)->first();

        if (! $feature || $feature->type != 'limit') {
            return false;
        }

        $usage = $this->usages()->code($featureCode)->first();

        if (! $usage) {
            $usage = $this->usages()->save(new $usageModel([
                'code' => $featureCode,
                'used' => 0,
            ]));
        }

        if (! $feature->isUnlimited() && $usage->used + $amount > $feature->limit) {
            return false;
        }

        $remaining = (float) ($feature->isUnlimited()) ? -1 : $feature->limit - ($usage->used + $amount);

        event(new FeatureConsumed($this, $feature, $amount, $remaining));

        return $usage->update([
            'used' => (float) ($usage->used + $amount),
        ]);
    }

    /**
     * Reverse of the consume a feature method, if it is 'limit' type.
     *
     * @param  string  $featureCode The feature code. This feature has to be 'limit' type.
     * @param  float  $amount The amount consumed.
     * @return bool Wether the feature was consumed successfully or not.
     */
    public function unconsumeFeature(string $featureCode, float $amount): bool
    {
        $usageModel = PlanSubscriptionUsage::class;

        $feature = $this->features()->code($featureCode)->first();

        if (! $feature || $feature->type != 'limit') {
            return false;
        }

        $usage = $this->usages()->code($featureCode)->first();

        if (! $usage) {
            $usage = $this->usages()->save(new $usageModel([
                'code' => $featureCode,
                'used' => 0,
            ]));
        }

        $used = (float) ($feature->isUnlimited()) ? ($usage->used - $amount < 0) ? 0 : ($usage->used - $amount) : ($usage->used - $amount);
        $remaining = ((float) ($feature->isUnlimited()) ? -1 : ($used > 0)) ? ($feature->limit - $used) : $feature->limit;

        event(new FeatureUnconsumed($this, $feature, $amount, $remaining));

        return $usage->update([
            'used' => $used,
        ]);
    }

    /**
     * Get the amount used for a limit.
     *
     * @param  string  $featureCode The feature code. This feature has to be 'limit' type.
     * @return float|int|null Null if doesn't exist, integer with the usage.
     */
    public function getUsageOf(string $featureCode): float|int|null
    {
        $usage = $this->usages()->code($featureCode)->first();
        $feature = $this->features()->code($featureCode)->first();

        if (! $feature || $feature->type != 'limit') {
            return null;
        }

        if (! $usage) {
            return 0;
        }

        return (float) $usage->used;
    }

    /**
     * Get the amount remaining for a feature.
     *
     * @param  string  $featureCode The feature code. This feature has to be 'limit' type.
     * @return float|int The amount remaining.
     */
    public function getRemainingOf(string $featureCode): float|int
    {
        $usage = $this->usages()->code($featureCode)->first();
        $feature = $this->features()->code($featureCode)->first();

        if (! $feature || $feature->type != 'limit') {
            return 0;
        }

        if (! $usage) {
            return (float) ($feature->isUnlimited()) ? -1 : $feature->limit;
        }

        return (float) ($feature->isUnlimited()) ? -1 : ($feature->limit - $usage->used);
    }
}
