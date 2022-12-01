<?php

namespace Jojostx\Larasubs\Models\Concerns;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Jojostx\Larasubs\Events\SubscriptionCreated;
use Jojostx\Larasubs\Events\SubscriptionStarted;
use Jojostx\Larasubs\Events\SubscriptionTrialStarted;
use Jojostx\Larasubs\Models\Plan;
use Jojostx\Larasubs\Models\Subscription;

trait HasSubscriptions
{
    /**
     * Get the user's most recent subscription.
     */
    public function subscription(): MorphOne
    {
        return $this->morphOne(config('larasubs.models.subscription'), 'subscribable')
          ->ofMany('starts_at', 'MAX');
    }

    /**
     * The user may have many subscriptions.
     */
    public function subscriptions(): MorphMany
    {
        return $this->morphMany(config('larasubs.models.subscription'), 'subscribable');
    }

    /**
     * Get a subscription by slug.
     */
    public function getSubscriptionBySlug(string $subscriptionSlug): ?Subscription
    {
        return $this->subscriptions()->where('slug', $subscriptionSlug)->first();
    }

    /**
     * A model may have many active subscriptions.
     */
    public function activeSubscriptions(): Collection
    {
        return $this->subscriptions->reject->inactive();
    }

    /**
     * A model may have many inactive subscriptions.
     */
    public function inactiveSubscriptions(): Collection
    {
        return $this->subscriptions->reject->active();
    }

    /**
     * Get all plans for active subscriptions.
     */
    public function getPlansForActiveSubscriptions(): Collection
    {
        $planIds = $this->activeSubscriptions()->pluck('plan_id')->unique();

        return Plan::whereIn('id', $planIds)->get();
    }

    /**
     * Get all subscriptions with inactive plans.
     */
    public function getPlansForInactiveSubscriptions(): Collection
    {
        $planIds = $this->inactiveSubscriptions()->pluck('plan_id')->unique();

        return Plan::whereIn('id', $planIds)->get();
    }

    /**
     * Get all subscriptions with active plans.
     *
     * @todo
     */
    public function getSubscriptionsWithActivePlan(): Collection
    {
        return \collect([]);
    }

    /**
     * Get all plans for inactive subscriptions.
     *
     * @todo
     */
    public function getSubscriptionsWithInactivePlan(): Collection
    {
        return \collect([]);
    }

    /**
     * Create a new unsaved subscription to a new plan for the model.
     *
     * @throws InvalidArgumentException
     */
    public function newSubscription(
        Plan $plan,
        string $subscriptionName,
        Carbon $starts_at = null,
        Carbon $ends_at = null,
        bool $withoutTrial = false,
        bool $withoutGrace = false,
    ): Subscription {
        \throw_if(
            $starts_at && $ends_at && $starts_at->isAfter($ends_at),
            new InvalidArgumentException("The starts_at: [$starts_at] must not be after the ends_at: [$ends_at]")
        );

        $starts_at ??= now();
        $ends_at = $ends_at ?? $plan->calculateNextRecurrenceEnd($starts_at);

        if ($plan->hasTrialPeriod() && ! $withoutTrial) {
            $trial_ends_at = $plan->calculateTrialPeriodEnd($starts_at);
        }

        if ($plan->hasGracePeriod() && ! $withoutGrace) {
            $grace_ends_at = $plan->calculateGracePeriodEnd($ends_at);
        }

        $subscription = new Subscription([
            'name' => $subscriptionName,
            'slug' => Str::slug($subscriptionName),
            'plan_id' => $plan->getKey(),
            'starts_at' => $starts_at,
            'ends_at' => $ends_at,
            'trial_ends_at' => $trial_ends_at ?? null,
            'grace_ends_at' => $grace_ends_at ?? null,
        ]);

        return $subscription;
    }

    /**
     * Subscribe the model to a plan.
     */
    public function subscribeTo(
        Plan $plan,
        string $subscriptionName = '',
        Carbon $starts_at = null,
        Carbon $ends_at = null,
        bool $withoutTrial = false,
        bool $withoutGrace = false,
    ): ?Subscription {
        $subscription = $this->newSubscription(
            $plan,
            filled($subscriptionName) ? $subscriptionName : Str::random(25),
            $starts_at,
            $ends_at,
            $withoutTrial,
            $withoutGrace,
        );

        $saved = $this->subscriptions()->save($subscription);

        if ($saved) {
            SubscriptionCreated::dispatch($subscription);

            SubscriptionStarted::dispatchIf(
                ($subscription->starts_at->isCurrentDay() || $subscription->starts_at->isPast()),
                $subscription
            );

            SubscriptionTrialStarted::dispatchIf(
                $subscription->onTrial() &&
                  ($subscription->starts_at->isCurrentDay() || $subscription->starts_at->isPast()),
                $subscription
            );
        }

        return $saved ? $subscription : null;
    }

    /**
     * Check if the user subscribed to a plan.
     */
    public function hasSubscriptionTo(Plan $plan): bool
    {
        return $this->subscription()
          ->where('plan_id', $plan->getKey())
          ->exists();
    }

    /**
     * Check if the user has an active subscription to a plan.
     */
    public function hasActiveSubscriptionTo(Plan $plan): bool
    {
        return $this->subscriptions()
          ->where('plan_id', $plan->getKey())
          ->whereActive()
          ->exists();
    }

    /**
     * Check if the user has inactive subscription to a plan.
     */
    public function hasInactiveSubscriptionTo(Plan $plan): bool
    {
        return $this->subscriptions()
          ->where('plan_id', $plan->getKey())
          ->whereNotActive()
          ->exists();
    }

    /**
     * Check if the user is subscribed to a plan.
     *
     * (alias to the hasSubscriptionTo method)
     */
    public function isSubscribedTo(Plan $plan): bool
    {
        return $this->hasSubscriptionTo($plan);
    }

    /**
     * Check if the user is not subscribed to a plan.
     *
     * (inverts the result from isSubscribedTo)
     */
    public function isNotSubscribedTo(Plan $plan): bool
    {
        return ! $this->isSubscribedTo($plan);
    }

    /**
     * Check if the user has an active subscription to a plan.
     */
    public function isActivelySubscribedTo(Plan $plan): bool
    {
        return $this->hasActiveSubscriptionTo($plan);
    }

    /**
     * Check if the user does not have an active subscription to a plan.
     */
    public function isNotActivelySubscribedTo(Plan $plan): bool
    {
        return ! $this->isActivelySubscribedTo($plan);
    }
}
