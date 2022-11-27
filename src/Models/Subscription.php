<?php

namespace Jojostx\Larasubs\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Jojostx\Larasubs\Events;
use Jojostx\Larasubs\Models\Concerns;
use Jojostx\Larasubs\Models\Scopes\EndsWithGracePeriodScope;
use Jojostx\Larasubs\Models\Scopes\StartingScope;
use Jojostx\Larasubs\Models\Scopes\CancellingScope;
use Jojostx\Larasubs\Services\Period;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

class Subscription extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasTranslations;
    use Concerns\EndsAndHasGracePeriod;
    use Concerns\Starts;
    use Concerns\Cancels;
    use Concerns\HasFeatures;
    use Concerns\HasSlug;

    /**
     * Subscription statuses
     */
    const STATUS_ENDED      = 'ended';
    const STATUS_ACTIVE     = 'active';
    const STATUS_CANCELLED   = 'cancelled';

    protected $dates = [
        'grace_period_ends_at',
        'trial_ends_at',
        'starts_at',
        'ends_at',
        'cancels_at',
        'cancelled_at',
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'trial_ends_at',
        'grace_period_ends_at',
        'starts_at',
        'ends_at',
        'cancels_at',
        'cancelled_at',
        'timezone',
        'sort_order',
    ];

    /**
     * {@inheritdoc}
     */
    protected $casts = [
        'slug' => 'string',
        'trial_ends_at' => 'datetime',
        'grace_period_ends_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'cancels_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    public $translatable = ['name', 'description', 'slug'];

    public $sortable = [
        'order_column_name' => 'sort_order',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('larasubs.tables.subscriptions') ?? parent::getTable();
    }

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->doNotGenerateSlugsOnUpdate()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getStatus(),
        )->shouldCache();
    }

    public function plan()
    {
        return $this->belongsTo(config('larasubs.models.plan'));
    }

    public function subscriber()
    {
        return $this->morphTo('subscribable');
    }

    /**
     * Find by subscribable id.
     */
    public function scopeWhereSubscriber(Builder $query, Model $subscribable): Builder
    {
        return $query
            ->where('subscribable_id', $subscribable->getKey())
            ->where('subscribable_type', $subscribable->getMorphClass());
    }

    /**
     * Scope models by plan id.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param int                                   $planId
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWherePlanId(Builder $query, int $planId): Builder
    {
        return $query->where('plan_id', $planId);
    }

    public function scopeNotActive(Builder $query): Builder
    {
        return $query->withoutGlobalScopes([
            EndsWithGracePeriodScope::class,
            StartingScope::class,
            CancellingScope::class,
        ])->where(function (Builder $query) {
            $query->where(fn (Builder $query) => $query->onlyEnded())
                ->orWhere(fn (Builder $query) => $query->onlyNotStarted())
                ->orWhere(fn (Builder $query) => $query->onlyCancelled());
        });
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->whereNotNull('cancelled_at');
    }

    public function scopeNotCancelled(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at');
    }

    /**
     * Find subscription with an ended trial.
     */
    public function scopeWhereTrialEnded(Builder $query): Builder
    {
        return $query->where('trial_ends_at', '<=', date('Y-m-d H:i:s'));
    }

    /**
     * Find ended subscriptions.
     */
    public function scopeWhereEndedBefore(Builder $query, Carbon $date = null): Builder
    {
        /**
         * - we construct a where clause to check if `ends_at` is before `$date`
         * - if `grace_period_ends_at` is null, set its value to `$date`
         * - construct a where clause to check if `grace_period_ends_at` is before the `$date`.
         * the same logic is replicated in the [isOverdue accessor method]
         */
        $date = $date ?? Carbon::now();

        return $query
            ->where('ends_at', '<=', $date)
            ->whereRaw("COALESCE(grace_period_ends_at, ?) <= ?", [$date, $date]);
    }

    /**
     * Find subscription with an ending trial.
     */
    public function scopeWhereEndingTrialInDaysFromNow(Builder $query, $dayRange = 3): Builder
    {
        $from = Carbon::now();
        $to = Carbon::now()->addDays($dayRange);

        return $query->whereBetween('trial_ends_at', [$from, $to]);
    }

    /**
     * Find ending subscriptions.
     */
    public function scopeByEndingInDaysFromNow(Builder $query, $dayRange = 3): Builder
    {
        $from = Carbon::now();
        $to = Carbon::now()->addDays($dayRange);

        return $query->whereBetween('ends_at', [$from, $to]);
    }

    /**
     * Find ending subscriptions.
     */
    public function scopeByGracePeriodInDaysFromNow(Builder $query, $dayRange = 3): Builder
    {
        $from = Carbon::now();
        $to = Carbon::now()->addDays($dayRange);

        return $query->whereBetween('grace_period_ends_at', [$from, $to]);
    }

    /**
     * Check if subscription is active.
     */
    public function isActive(): bool
    {
        if ((!$this->isEnded() || $this->onTrial()) || !$this->isCancelledImmediately()) {
            return true;
        }

        return false;
    }

    /**
     * Check if subscription is inactive.
     */
    public function isInactive(): bool
    {
        return !$this->isActive();
    }

    /**
     * Check if subscription is active.
     * (alias for the isActive method)
     */
    public function active(): bool
    {
        return $this->isActive();
    }

    /**
     * Check if subscription is inactive.
     * (alias for the isInactive method)
     */
    public function inactive(): bool
    {
        return $this->isInactive();
    }

    /**
     * Check if subscription is trialling.
     */
    public function onTrial(): bool
    {
        return !is_null($this->trial_ends_at) ? $this->trial_ends_at->isPast() : false;
    }

    /**
     * Check if subscription is cancelled.
     */
    public function isCancelled(): bool
    {
        return !is_null($this->cancelled_at) ? $this->cancelled_at->isPast() : false;
    }

    /**
     * Check if subscription is cancelled immediately.
     */
    public function isCancelledImmediately(): bool
    {
        if (empty($this->cancels_at) || empty($this->cancelled_at)) {
            return false;
        }

        return $this->cancelled_at->isPast() &&
            $this->cancels_at->isPast() &&
            $this->cancels_at->isSameDay($this->cancelled_at);
    }

    /**
     * Check if subscription period has ended.
     */
    public function isEnded(): bool
    {
        return !is_null($this->ends_at) ? $this->ends_at->isPast() : false;
    }

    /**
     * Check if subscription period and grace period has ended.
     */
    public function isOverdue(): bool
    {
        if ($this->grace_period_ends_at) {
            return $this->ends_at->isPast()
                && $this->grace_period_ends_at->isPast();
        }

        return $this->ends_at->isPast();
    }

    protected function syncPlanFeatureUsage(Plan $plan)
    {
        $features_new_plan = $plan->features()->pluck('id');

        // update the ends_at for all the usages of the subscription
        // that are available on the new plan
        $this->usage()
            ->getBaseQuery()
            ->whereIn('feature_id', $features_new_plan)
            ->update([
                'ends_at' => $this->ends_at
            ]);

        // delete all usage for this subscription that are not available on the new plan
        $this->usage()
            ->whereNotIn('feature_id', $features_new_plan)
            ->delete();
    }

    /**
     * Change subscription plan.
     *
     * @param \Jojostx\Larasubs\Models\Plan $plan
     *
     * @return $this
     */
    public function changePlan(Plan $plan, bool $sync = true)
    {
        // If the plans do not have the same billing frequency
        // (e.g., interval and interval_type, we will update
        // the billing dates starting today, and since we are basically creating
        // a new billing cycle, the usage data will be cleared.
        if ($this->plan->interval !== $plan->interval || $this->plan->interval_type !== $plan->interval_type) {
            $this->setNewPeriod($plan->interval_type, $plan->interval);
        }

        // sync or delete all related usages for this subscription based on the new plan
        $sync ? $this->syncPlanFeatureUsage($plan) : $this->usage()->delete();

        $old_plan = $this->plan;
        // Attach new plan to subscription
        $this->plan_id = $plan->getKey();
        $this->save();

        event(new Events\SubscriptionPlanChanged($this, $old_plan, $plan));

        return $this;
    }

    /**
     * Start subscription at the given date and end it at the given endDate.
     */
    public function start(?Carbon $startDate = null, ?Carbon $endDate = null): bool
    {
        $startDate = $startDate ?: today();

        if (empty($endDate) || $startDate->isAfter($endDate)) {
            $this->setNewPeriod(starts_at: $startDate);
        } else {
            $this->starts_at = $startDate;
            $this->ends_at = $endDate;
        }

        $saved = $this->save();

        if (!$saved) {
            return false;
        }

        if ($startDate->isToday()) {
            event(new Events\SubscriptionStarted($this));
        } elseif ($startDate->isFuture()) {
            event(new Events\SubscriptionScheduled($this));
        }

        return true;
    }

    /**
     * Renew a subscription.
     */
    public function renew(): bool
    {
        if ($this->isEnded()) {
            return false;
        }

        $this->setNewPeriod();

        $updated = $this->update([
            'cancelled_at' => null,
            'cancels_at' => null,
        ]);

        $updated && event(new Events\SubscriptionRenewed($this));

        return $updated;
    }

    /** 
     * Cancel a subscription
     */
    public function cancel(?Carbon $cancelDate = null, $immediately = false): bool
    {
        $this->cancelled_at = $cancelDate ?? now();

        if ($immediately) {
            $this->ends_at = $this->cancelled_at;
            $this->cancels_at = $this->cancelled_at;
        }

        $saved = $this->save();

        $saved && event(new Events\SubscriptionCancelled($this));

        return $saved;
    }

    /** 
     * Reactivate a cancelled subscription
     */
    public function reactivate(): bool
    {
        if ($this->isCancelled() && !$this->ended()) {
            $this->cancelled_at = null;
            $this->cancels_at = null;

            $saved = $this->save();

            $saved && event(new Events\SubscriptionCancelled($this));

            return $saved;
        }

        return false;
    }

    /**
     * Set a new subscription period.
     */
    public function setNewPeriod(string $interval_type = '', int $interval = 1, string|Carbon $starts_at = '')
    {
        if (empty($interval_type)) {
            $interval_type = $this->plan->interval_type;
        }

        if (empty($interval)) {
            $interval = $this->plan->interval;
        }

        $period = new Period($interval_type, $interval, $starts_at);

        $this->starts_at = $period->getStartDate();
        $this->ends_at = $period->getEndDate();

        return $this;
    }

    private function getRenewedEnd(?Carbon $endDate = null): Carbon
    {
        if (!empty($endDate)) {
            return $endDate;
        }

        if ($this->isOverdue()) {
            return $this->plan->calculateNextRecurrenceEnd();
        }

        return $this->plan->calculateNextRecurrenceEnd($this->ends_at);
    }

    protected function getStatus()
    {
        if ($this->isActive()) {
            return self::STATUS_ACTIVE;
        }

        if ($this->isCancelled()) {
            return self::STATUS_CANCELLED;
        }

        if ($this->isEnded()) {
            return self::STATUS_ENDED;
        }
    }
}
