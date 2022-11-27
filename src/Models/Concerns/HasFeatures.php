<?php

namespace Jojostx\Larasubs\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Jojostx\Larasubs\Events\FeatureUsed;
use Jojostx\Larasubs\Models\Feature;
use Jojostx\Larasubs\Models\FeatureSubscription;
use OutOfBoundsException;
use OverflowException;

trait HasFeatures
{
  /**
   * The subscription may have many feature usage.
   */
  public function usage(): HasMany
  {
    $model = (config('larasubs.models.subscription'));
    $model = new $model;

    return $this->hasMany(config('larasubs.models.feature_subscription'), $model->getForeignKey(), $model->getKeyName());
  }

  protected function features(): Attribute
  {
    return Attribute::make(
      get: fn () => $this->loadFeatures(),
    )->shouldCache();
  }

  protected function loadFeatures(): Collection
  {
    $this->loadMissing('plan.features');

    return $this->plan->features ?? collect();
  }

  public function canUseFeature(string $featureSlug, ?float $units = null): bool
  {
    if (empty($feature = $this->getFeatureBySlug($featureSlug))) {
      return false;
    }

    // If the feature is not a consumable type, let's return true
    if (!$feature->consumable) {
      return true;
    }

    $featureUsage = $this->getUsageByFeatureId($feature->getKey());

    if (!$featureUsage || $featureUsage->ended()) {
      return false;
    }

    // Check for available units
    $remainingUnits = $this->getRemainingUnitsForFeature($featureSlug);

    return $remainingUnits >= $units;
  }

  public function cantUseFeature(string $featureSlug, ?float $units = null): bool
  {
    return !$this->canUseFeature($featureSlug, $units);
  }

  public function missingFeature(string $featureSlug): bool
  {
    return empty($this->getFeatureBySlug($featureSlug));
  }

  public function hasFeature(string $featureSlug): bool
  {
    return !$this->missingFeature($featureSlug);
  }

  /**
   * @throws OutOfBoundsException
   * @throws OverflowException
   */
  protected function useUnitsOnFeature(string $featureSlug, float $units, bool $incremental = true): ?FeatureSubscription
  {
    $this->validateFeature($featureSlug, $units);

    $feature = $this->getFeatureBySlug($featureSlug);

    /** @var FeatureSubscription */
    $featureUsage = $this->getUsageByFeatureId($feature->getKey());

    $featureUsage->feature()->associate($feature);

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

    $featureUsage->used = ($incremental ? $featureUsage->used + $units : $units);

    $featureUsage->save() && event(new FeatureUsed($this, $feature, $units));

    return $featureUsage;
  }

  /**
   * @throws OutOfBoundsException
   * @throws OverflowException
   */
  public function setUsedUnitsOnFeature(string $featureSlug, float $units): ?FeatureSubscription
  {
    return $this->useUnitsOnFeature($featureSlug, $units, false);
  }

  public function getRemainingUnitsForFeature(string $featureSlug): float
  {
    return $this->getMaxFeatureUnits($featureSlug) - $this->getFeatureUnitsUsed($featureSlug);
  }

  public function getMaxFeatureUnits(string $featureSlug): float
  {
    $feature = $this->plan->features()->where('slug', $featureSlug)->first();

    return $feature->units ?? 0;
  }

  public function getFeatureUnitsUsed(string $featureSlug): float
  {
    $usage = $this->usage()->whereFeatureSlug($featureSlug)->first();

    return (is_null($usage) || $usage->ended()) ? 0 : $usage->used;
  }

  public function getFeatureBySlug(string $featureSlug): ?Feature
  {
    $feature = $this->features->firstWhere('slug', $featureSlug);

    return $feature;
  }

  public function getUsageByFeatureId(string $feature_id): ?FeatureSubscription
  {
    return $this->usage()
      ->where('feature_id', $feature_id)
      ->firstOrNew();
  }

  public function validateFeature(string $featureSlug, float $units)
  {
    throw_if($this->missingFeature($featureSlug), new OutOfBoundsException(
      'None of the active plans grants access to this feature.',
    ));

    throw_if($this->cantUseFeature($featureSlug, $units), new OverflowException(
      'The feature does not have enough units.',
    ));
  }
}
