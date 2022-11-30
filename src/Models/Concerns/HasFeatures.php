<?php

namespace Jojostx\Larasubs\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Jojostx\Larasubs\Events\FeatureUsed;
use Jojostx\Larasubs\Models\Exceptions\CannotUseFeatureException;
use Jojostx\Larasubs\Models\Exceptions\FeatureNotFoundException;
use Jojostx\Larasubs\Models\Feature;
use Jojostx\Larasubs\Models\FeatureSubscription;

trait HasFeatures
{
  /**
   * The subscription may have many feature usage.
   */
  public function usage(): HasMany
  {
    $subscription = (config('larasubs.models.subscription'));
    $subscription = new $subscription;

    return $this->hasMany(config('larasubs.models.feature_subscription'), $subscription->getForeignKey(), $subscription->getKeyName());
  }

  /**
   * The features for the subscription.
   */
  protected function features(): Attribute
  {
    return Attribute::make(
      get: fn () => $this->loadFeatures(),
    )->shouldCache();
  }

  /**
   * Load the features from the plan for the subscription.
   */
  protected function loadFeatures(): Collection
  {
    $this->loadMissing('plan.features');

    return $this->plan->features ?? collect();
  }

  /**
   * get a feature by its slug from the features collection
   */
  protected function getFeatureBySlug(string|Feature $featureSlug): ?Feature
  {
    $featureSlug = $this->getFeatureSlug($featureSlug);

    $feature = $this->features->firstWhere('slug', $featureSlug);

    return $feature;
  }

  /**
   * get a feature from the features collection by id
   */
  protected function getFeatureById(int $featureId): ?Feature
  {
    $feature = $this->features->firstWhere('id', $featureId);

    return $feature;
  }

  /**
   * gets the slug for a feature.
   */
  protected function getFeatureSlug(string|Feature $featureSlug): string
  {
    return \is_string($featureSlug) ? $featureSlug : $featureSlug->slug;
  }

  /**
   * get the usage for a feature,
   * 
   * - If the feature is a valid feature, this method returns
   * an existing usage for the $featureKey or a newly created
   * usage with **used** units set to zero if no usage is found.
   */
  protected function getUsageByFeature(Feature $feature): ?FeatureSubscription
  {
    return $this->firstOrCreateUsage($feature);
  }

  /**
   * create or return the usage (feature_subscription) for the subscription.
   * usage can not be created for inactive features.
   */
  protected function firstOrCreateUsage(Feature $feature, int $used = 0): ?FeatureSubscription
  {
    // check if the feature exists on the features collection
    // [these are the features available for this Subscription's Plan]
    $feature = $this->getFeatureById($feature->getKey());

    if (is_null($feature) || $feature->isInactive()) {
      return null;
    };

    // create or return a record in the Feature_Subscription table (usage) 
    // for the subcription and for the $feature.
    $usage = $this->usage()
      ->firstOrCreate(
        [
          'subscription_id' => $this->getKey(),
          'feature_id' => $feature->getKey(),
        ],
        [
          'active' => true,
          'used' => $used,
          'ends_at' => $this->ends_at,
        ]
      );

    return $usage->refresh();
  }

  /**
   * throws an exception if the subscription cannot use the feature or 
   * the feature is not available on the subscriptions Plan
   * 
   * @throws FeatureNotFoundException|CannotUseFeatureException
   */
  protected function validateFeature(Feature $feature, int $units)
  {
    throw_if($this->missingFeature($feature), new FeatureNotFoundException($feature));

    if ($feature->isInactive()) {
      throw new CannotUseFeatureException("This feature is inactive for this plan: Only active feature can be used", $feature, $units);
    }

    $featureUsage = $this->getUsageByFeature($feature);
    if (is_null($featureUsage) || $featureUsage->isInactive()) {
      throw new CannotUseFeatureException("The use of this feature has been deactivated for this subscription", $feature, $units);
    }

    throw_if(
      $this->hasInsufficientBalance($feature, $units),
      new CannotUseFeatureException("This feature has insufficient balance for the use of the provided units", $feature, $units)
    );
  }

  /**
   * checks if a feature can be used for the subscription
   */
  public function canUseFeature(string|Feature $featureSlug, ?int $units = null): bool
  {
    // feature is not available for the Subscription's Plan
    if (empty($feature = $this->getFeatureBySlug($featureSlug))) {
      return false;
    }

    // This feature is inactive for this plan: Only active feature can be used
    if ($feature->isInactive()) {
      return false;
    }

    // feature usage is null or has been deactivated for this subscription
    $featureUsage = $this->getUsageByFeature($feature);
    if (is_null($featureUsage) || $featureUsage->isInactive()) {
      return false;
    }

    // If the feature is not a consumable type, let's return true
    if (!$feature->consumable) {
      return true;
    }

    // Check for available units
    return $this->getRemainingUnitsForFeature($feature) >= (int) $units;
  }

  /**
   * checks if a feature can not be used for the subscription
   */
  public function cannotUseFeature(string|Feature $featureSlug, ?int $units = null): bool
  {
    return !$this->canUseFeature($featureSlug, $units);
  }

  /**
   * checks if a feature is available for the subscription
   */
  public function hasFeature(string|Feature $featureSlug): bool
  {
    return !$this->missingFeature($featureSlug);
  }

  /**
   * checks if a feature is not available for the subscription
   */
  public function missingFeature(string|Feature $featureSlug): bool
  {
    return empty($this->getFeatureBySlug($featureSlug));
  }

  /**
   * checks if a feature has sufficient balance for use
   */
  public function hasSufficientBalance(Feature $feature, ?int $units = null): bool
  {
    if (empty($this->getFeatureBySlug($feature->slug))) {
      return false;
    };

    return $this->getRemainingUnitsForFeature($feature) >= (int) $units;
  }

  /**
   * checks if a feature has insufficient balance for use
   */
  public function hasInsufficientBalance(Feature $feature, ?int $units = null): bool
  {
    return !$this->hasSufficientBalance($feature, $units);
  }

  /**
   * get the remaining units for the feature usage
   */
  public function getRemainingUnitsForFeature(string|Feature $featureSlug): int
  {
    return $this->getMaxFeatureUnits($featureSlug) - $this->getUnitsUsedForFeature($featureSlug);
  }

  /**
   * get the maximum units that can be used on a feature
   */
  public function getMaxFeatureUnits(string|Feature $featureSlug): int
  {
    $featureSlug = $this->getFeatureSlug($featureSlug);

    $feature = $this->plan->features()->where('slug', $featureSlug)->first();

    return $feature->pivot->units ?? 0;
  }

  /**
   * get the units that have been used on a feature
   */
  public function getUnitsUsedForFeature(string|Feature $featureSlug): int
  {
    $featureSlug = $this->getFeatureSlug($featureSlug);

    $usage = $this->usage()->whereFeatureSlug($featureSlug)->first();

    return (is_null($usage) || $usage->ended()) ? 0 : $usage->used;
  }

  /**
   * use the given **$units** on the usage (feature_subscription) for the subscription
   * 
   * @param bool $increments pass false to override the current units on the usage
   * 
   * @throws FeatureNotFoundException|CannotUseFeatureException
   */
  public function useUnitsOnFeature(Feature $feature, int $units = 0, bool $increments = true): ?FeatureSubscription
  {
    $this->validateFeature($feature, $units);

    /** @var FeatureSubscription */
    $featureUsage = $this->getUsageByFeature($feature);

    if ($feature->interval_type && $feature->interval) {
      // Set expiration date when the usage record is new or doesn't have one.
      if (!$featureUsage->ended()) {
        // Set date from subscription creation date so the reset
        // period match the period specified by the subscription's plan.
        $featureUsage->ends_at = $feature->calculateNextRecurrenceEnd($this->created_at);
      } elseif ($featureUsage->ended()) {
        // If the usage record has been expired, let's assign
        // a new expiration date and reset the uses to zero.
        $featureUsage->ends_at = $feature->calculateNextRecurrenceEnd($featureUsage->ends_at);
        $featureUsage->used = 0;
      }
    }

    if ($feature->consumable) {
      $featureUsage->used = ($increments ? $featureUsage->used + $units : $units);
    }

    FeatureUsed::dispatchIf($featureUsage->save(), $this, $feature, $units);

    return $featureUsage;
  }

  /**
   * set the given **$units** on the usage (feature_subscription) for the subscription 
   *
   * @throws FeatureNotFoundException|CannotUseFeatureException
   */
  public function setUsedUnitsOnFeature(Feature $feature, int $units = 0): ?FeatureSubscription
  {
    return $this->useUnitsOnFeature($feature, $units, false);
  }

  /**
   * activate a feature for this model
   * - this activates the usage (feature_subscription) 
   */
  public function activateFeature(string|Feature $featureSlug): bool
  {
    if ($usage = $this->firstOrCreateUsage($featureSlug)) {
      return $usage->activate();
    }

    return false;
  }

  /**
   * deactivate a feature for this model
   * - this deactivates the usage (feature_subscription) 
   */
  public function deactivateFeature(string|Feature $featureSlug): bool
  {
    if ($usage = $this->firstOrCreateUsage($featureSlug)) {
      return $usage->deactivate();
    }

    return false;
  }
}
