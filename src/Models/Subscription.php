<?php

namespace Jojostx\Larasubs\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Jojostx\Larasubs\Events;
use Jojostx\Larasubs\Models\Concerns;
use Jojostx\Larasubs\Services\Period;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\DB;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

class Subscription extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasTranslations;
    use HasSlug;
    use Concerns\EndsAndHasGracePeriod;
    use Concerns\HasFeatures;

    /**
     * Subscription statuses
     */
    const STATUS_ENDED      = 'ended';
    const STATUS_OVERDUE    = 'overdue';
    const STATUS_ACTIVE     = 'active';
    const STATUS_CANCELLED  = 'cancelled';

    protected $dates = [
        'grace_ends_at',
        'trial_ends_at',
        'starts_at',
        'ends_at',
        'cancels_at',
    ];

    protected $fillable = [
        'plan_id',
        'name',
        'slug',
        'description',
        'trial_ends_at',
        'grace_ends_at',
        'starts_at',
        'ends_at',
        'cancels_at',
        'timezone',
        'sort_order',
    ];

    protected $casts = [
        'slug' => 'string',
        'trial_ends_at' => 'datetime',
        'grace_ends_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'cancels_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    /**
     * The attributes that should be translatable
     */
    public $translatable = [
        'name',
        'description',
    ];

    /**
     * The attributes that should be sortable
     */
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
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    /**
     * attribute for the status of a subscription
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getStatus(),
        )->shouldCache();
    }

    /**
     * get the status of a subscription as a string
     */
    protected function getStatus()
    {
        if ($this->isActive()) {
            return self::STATUS_ACTIVE;
        }

        if ($this->isCancelled()) {
            return self::STATUS_CANCELLED;
        }

        if ($this->isOverdue()) {
            return self::STATUS_OVERDUE;
        }

        if ($this->isEnded()) {
            return self::STATUS_ENDED;
        }
    }

    /**
     * get the Plan model for the subscription
     */
    public function plan()
    {
        return $this->belongsTo(config('larasubs.models.plan'));
    }

    /**
     * get the Subscriber model the subscription
     */
    public function subscriber()
    {
        return $this->morphTo('subscribable');
    }

    /**
     * Scope query to return only subscriptions for the Plan.
     */
    public function scopeWherePlan(Builder $query, Plan $plan): Builder
    {
        return $query->where('plan_id', $plan->getKey());
    }

    /**
     * Scope query to return only subscriptions for the subscribable model.
     */
    public function scopeWhereSubscriber(Builder $query, Model $subscribable): Builder
    {
        return $query
            ->where('subscribable_id', $subscribable->getKey())
            ->where('subscribable_type', $subscribable->getMorphClass());
    }

    /**
     * Scope query to return only active subscriptions.
     * 
     * (active means the subscription has started,
     * is not overdue and is not cancelled)
     */
    public function scopeWhereActive(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->where(fn (Builder $query) => $query->whereStarted())
                ->where(fn (Builder $query) => $query->whereNotEnded())
                ->where(fn (Builder $query) => $query->whereNotCancelled());
        });
    }

    /**
     * Scope query to return only inactive subscriptions.
     * 
     * (not active means the subscription is overdue
     * or it has not started, or it has been cancelled)
     */
    public function scopeWhereNotActive(Builder $query): Builder
    {
        return $query->where(fn (Builder $query) => $query->whereOverdue())
            ->orWhere(fn (Builder $query) => $query->whereNotStarted())
            ->orWhere(fn (Builder $query) => $query->whereCancelled());
    }

    /**
     * Scope query to return only started subscriptions.
     */
    public function scopeWhereStarted(Builder $query): Builder
    {
        return  $query->where('starts_at', '<=', now());
    }

    /**
     * Scope query to return subscriptions that have not started.
     */
    public function scopeWhereNotStarted(Builder $query): Builder
    {
        return  $query->where('starts_at', '>', now());
    }

    /**
     * Scope query to return subscriptions that have been cancelled.
     */
    public function scopeWhereCancelled(Builder $query): Builder
    {
        return $query->whereNotNull('cancels_at');
    }

    /**
     * Scope query to return subscriptions that have not been cancelled.
     */
    public function scopeWhereNotCancelled(Builder $query): Builder
    {
        return $query->whereNull('cancels_at');
    }

    /**
     * Scope query to return subscriptions that are on trial.
     */
    public function scopeWhereOnTrial(Builder $query): Builder
    {
        return $query
            ->where('trial_ends_at', '>', now())
            ->whereColumn('trial_ends_at', 'starts_at');
    }

    /**
     * Scope query to return subscriptions with an ended trial.
     */
    public function scopeWhereTrialEnded(Builder $query): Builder
    {
        return $query->where('trial_ends_at', '<=', date('Y-m-d H:i:s'));
    }

    /**
     * Scope query to return subscriptions that are overdue.
     */
    public function scopeWhereOverdue(Builder $query): Builder
    {
        /**
         * - we construct a where clause to check if `ends_at` is before `$date`
         * - if `grace_ends_at` is null, set its value to `$date`
         * - construct a where clause to check if `grace_ends_at` is before the `$date`.
         * the same logic is replicated in the [isOverdue accessor method]
         */
        $date = Carbon::now();

        return $query
            ->where('ends_at', '<', $date)
            ->whereRaw("COALESCE(grace_ends_at, ?) <= ?", [$date, $date]);
    }

    /**
     * Scope query to return subscriptions have ended.
     * (also includes overdue subscriptions)
     */
    public function scopeWhereEnded(Builder $query): Builder
    {
        $date = Carbon::now();

        return $query
            ->where('ends_at', '<', $date);
    }

    /**
     * Scope query to return subscriptions have not ended.
     */
    public function scopeWhereNotEnded(Builder $query): Builder
    {
        $date = Carbon::now();

        return $query
            ->where('ends_at', '>', $date);
    }

    /**
     * Scope query to return subscriptions that are overdue 
     * before the provided date.
     */
    public function scopeWhereOverdueBefore(Builder $query, Carbon $date = null): Builder
    {
        /**
         * - we construct a where clause to check if `ends_at` is before `$date`
         * - if `grace_ends_at` is null, set its value to `$date`
         * - construct a where clause to check if `grace_ends_at` is before the `$date`.
         * the same logic is replicated in the [isOverdue accessor method]
         */
        $date = $date ?? Carbon::now();

        return $query
            ->where('ends_at', '<=', $date)
            ->whereRaw("COALESCE(grace_ends_at, ?) <= ?", [$date, $date]);
    }

    /**
     * scope query to return subscriptions that ends
     * before a provided date.
     */
    public function scopeWhereEndedBefore(Builder $query, Carbon $date = null): Builder
    {
        $date = $date ?? Carbon::now();

        return $query
            ->where('ends_at', '<=', $date);
    }

    /**
     * Scope a query to return subscriptions that ends before the 
     * provided days from now.
     */
    public function scopeWhereEndsInDaysFromNow(Builder $query, int $dayRange = 3): Builder
    {
        $from = Carbon::now();
        $to = Carbon::now()->addDays($dayRange);

        return $query->whereBetween('ends_at', [$from, $to]);
    }

    /**
     * scope query to return subscriptions with trial period that ends before the
     * provided days from now.
     */
    public function scopeWhereTrialEndsInDaysFromNow(Builder $query, int $dayRange = 3): Builder
    {
        $from = Carbon::now();
        $to = Carbon::now()->addDays($dayRange);

        return $query->whereBetween('trial_ends_at', [$from, $to]);
    }

    /**
     * scope query to return subscriptions with grace period that ends before the
     * provided days from now.
     */
    public function scopeWhereGraceEndsInDaysFromNow(Builder $query, int $dayRange = 3): Builder
    {
        $from = Carbon::now();
        $to = Carbon::now()->addDays($dayRange);

        return $query->whereBetween('grace_ends_at', [$from, $to]);
    }

    /**
     * sync all the feature usages based on the given plan.
     * - updates the ends_at date for feature usages available on the given plan.
     * - deletes all features that are not available on the given plan
     */
    protected function syncPlanFeatureUsage(Plan $plan)
    {
        $new_plan_features = $plan->features()->pluck('features.id');

        // update the ends_at for all the usages of the subscription
        // that are available on the new plan
        $this->usage()
            ->whereIn('feature_id', $new_plan_features)
            ->update([
                'ends_at' => $this->ends_at
            ]);

        // delete all usage for this subscription that are not available on the new plan
        $this->usage()
            ->whereNotIn('feature_id', $new_plan_features)
            ->delete();
    }

    /**
     * Change subscription plan.
     * - set $sync to false to disable syncing of the feature usages
     * and instead delete all feature usages.
     * - set $sync to true to enable syncing of the feature usages based
     * on the new plan.
     */
    public function changePlan(Plan $plan, bool $sync = true)
    {
        $old_plan = $this->plan;

        $saved = DB::transaction(function () use ($plan, $sync) {
            // If the plans do not have the same billing frequency
            // (e.g., interval and interval_type, we will update
            // the billing dates starting today, and since we are basically creating
            // a new billing cycle, the usage data will be cleared.
            if ($this->plan->interval !== $plan->interval || $this->plan->interval_type !== $plan->interval_type) {
                $this->setNewPeriod($plan->interval_type, $plan->interval);
            }

            // sync or delete all related usages for this subscription based on the new plan
            $sync ? $this->syncPlanFeatureUsage($plan) : $this->usage()->delete();

            // Attach new plan to subscription
            return $this->forceFill([
                'plan_id' => $plan->getKey()
            ])->save();
        });

        Events\SubscriptionPlanChanged::dispatchIf($saved, $this, $old_plan, $plan);

        return $this;
    }

    /**
     * Start subscription at $startDate and end it at $endDate.
     */
    public function start(?Carbon $startDate = null, ?Carbon $endDate = null): bool
    {
        $startDate = $startDate ?: now();

        if (empty($endDate) || $startDate->isAfter($endDate)) {
            $this->setNewPeriod(starts_at: $startDate);
        } else {
            $this->starts_at = $startDate;
            $this->ends_at = $endDate;
        }

        $saved = $this->save();

        if ($saved) {
            if ($startDate->isToday()) {
                Events\SubscriptionStarted::dispatch($this);
            } elseif ($startDate->isFuture()) {
                Events\SubscriptionScheduled::dispatch($this);
            }
        }

        return $saved;
    }

    /**
     * Renew a subscription.
     * - can only renew a subscription that has ended or is on grace period
     */
    public function renew(?Carbon $endDate = null): bool
    {
        if (!$this->isEnded()) {
            return false;
        }

        $endDate = $this->getRenewedEnd($endDate);

        $updated = $this->update([
            'ends_at' => $endDate,
            'cancels_at' => null,
        ]);

        Events\SubscriptionRenewed::dispatchIf($updated, $this);

        return $updated;
    }

    /** 
     * Cancel a subscription
     * - sets the **cancels_at** attribute to the $cancelDate.
     * - if $immediately is set to true, the **ends_at** attribute 
     * is set to the $cancelDate.
     * - if $cancelDate is not given, the current date is used.
     */
    public function cancel(?Carbon $cancelDate = null, $immediately = false): bool
    {
        $this->cancels_at = $cancelDate ?? now();

        if ($immediately) {
            $this->ends_at = $this->cancels_at;
        }

        $saved = $this->save();

        Events\SubscriptionCancelled::dispatchIf($saved, $this, $immediately);

        return $saved;
    }

    /** 
     * Cancel a subscription immediately
     * - sets **ends_at** and **cancels_at** attribute to $cancelDate
     * - if $cancelDate is not given defaults the current date.
     */
    public function cancelImmediately(?Carbon $cancelDate = null): bool
    {
        $this->cancels_at = $cancelDate ?? now();
        $this->ends_at = $this->cancels_at;

        $saved = $this->save();

        Events\SubscriptionCancelled::dispatchIf($saved, $this, true);

        return $saved;
    }

    /** 
     * Reactivate a cancelled subscription
     * - can only activate a cancelled subscription that has not ended
     */
    public function reactivate(): bool
    {
        if ($this->isCancelled() && !$this->ended()) {
            $this->cancels_at = null;

            $saved = $this->save();

            Events\SubscriptionReactivated::dispatchIf($saved, $this);

            return $saved;
        }

        return false;
    }

    protected function getRenewedEnd(?Carbon $endDate = null): Carbon
    {
        if (!empty($endDate)) {
            return $endDate;
        }

        if ($this->isOverdue()) {
            return $this->plan->calculateNextRecurrenceEnd();
        }

        return $this->plan->calculateNextRecurrenceEnd($this->ends_at);
    }

    /**
     * Set a new subscription period
     * - It does not persist the dates to the **DB**
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

    /**
     * Check if subscription has started.
     */
    public function started()
    {
        if (empty($this->starts_at)) {
            return false;
        }

        return $this->starts_at->isPast();
    }

    /**
     * Check if subscription has not started.
     */
    public function notStarted()
    {
        return !$this->started();
    }

    /**
     * Check if subscription is active.
     */
    public function isActive(): bool
    {
        return !($this->isEnded() || $this->isCancelledImmediately());
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
        return !is_null($this->trial_ends_at) ? $this->trial_ends_at->isFuture() : false;
    }

    /**
     * Check if subscription is cancelled.
     */
    public function isCancelled(): bool
    {
        return !is_null($this->cancels_at);
    }

    /**
     * Check if subscription is not cancelled.
     */
    public function notCancelled()
    {
        return !$this->cancelled();
    }

    /**
     * Check if subscription is cancelled and is past its cancel date
     */
    public function isPastCancelled()
    {
        if (empty($this->cancels_at)) {
            return false;
        }

        return $this->cancels_at->isPast();
    }

    /**
     * Check if subscription is cancelled immediately.
     */
    public function isCancelledImmediately(): bool
    {
        if (empty($this->cancels_at) || empty($this->ends_at)) {
            return false;
        }

        return $this->cancels_at->isSameDay($this->ends_at);
    }

    /**
     * Check if subscription is cancelled immediately and is past its cancel date.
     */
    public function isPastCancelledImmediately(): bool
    {
        if (empty($this->cancels_at) || empty($this->ends_at)) {
            return false;
        }

        return $this->cancels_at->isPast() &&
            $this->cancels_at->isSameDay($this->ends_at);
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
        if ($this->grace_ends_at) {
            return $this->ends_at->isPast()
                && $this->grace_ends_at->isPast();
        }

        return $this->ends_at->isPast();
    }

    /**
     * Check if subscription period and grace period has ended.
     */
    public function isOnGracePeriod(): bool
    {
        if ($this->grace_ends_at) {
            return $this->ends_at->isPast()
                && $this->grace_ends_at->isFuture();
        }

        return false;
    }
}
