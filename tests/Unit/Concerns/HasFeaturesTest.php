<?php

namespace Jojostx\Larasubs\Tests\Unit\Concerns;

use LucasDotVin\DBQueriesCounter\Traits\CountsQueries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Jojostx\Larasubs\Events\FeatureUsed;
use Jojostx\Larasubs\Models\Exceptions\CannotUseFeatureException;
use Jojostx\Larasubs\Models\Exceptions\FeatureNotFoundException;
use Jojostx\Larasubs\Models\Feature;
use Jojostx\Larasubs\Models\FeatureSubscription;
use Jojostx\Larasubs\Models\Plan;
use Jojostx\Larasubs\Tests\Fixtures\Models\User;
use Jojostx\Larasubs\Tests\TestCase;

class HasFeaturesTest extends TestCase
{
  use CountsQueries;
  use RefreshDatabase;
  use WithFaker;

  public function test_model_can_retrieve_usages()
  {
    $feature = Feature::factory()->create();

    $plan = Plan::factory()->create();
    $plan->features()->attach($feature);

    $subscriber = User::factory()->create();
    $subscription = $subscriber->subscribeTo($plan);

    //use a feature.
    $featureSubscriptionPivot = FeatureSubscription::factory()->create([
      'subscription_id' => $subscription->id,
      'feature_id' => $feature->id,
    ]);

    $this->assertEquals($subscription->features->first()->id, $featureSubscriptionPivot->feature->id);
    $this->assertTrue($subscription->usage->contains($featureSubscriptionPivot));
  }

  public function test_model_caches_features()
  {
    $units = $this->faker->numberBetween(5, 10);

    $plan = Plan::factory()->createOne();
    $feature = Feature::factory()->consumable()->createOne();
    $feature->plans()->attach($plan, [
      'units' => $units,
    ]);

    $subscriber = User::factory()->createOne();
    $subscription = $subscriber->subscribeTo($plan);

    $this->whileCountingQueries(fn () => $subscription->features);
    $initiallyPerformedQueries = $this->getQueryCount();

    $this->whileCountingQueries(fn () => $subscription->features);
    $totalPerformedQueries = $this->getQueryCount();

    $this->assertEquals($initiallyPerformedQueries, $totalPerformedQueries);
  }

  public function test_model_can_use_a_feature()
  {
    $units = $this->faker->numberBetween(5, 10);
    $usage = $this->faker->numberBetween(1, $units);

    $plan = Plan::factory()->createOne();
    $feature = Feature::factory()->consumable()->createOne();
    $feature->plans()->attach($plan, [
      'units' => $units,
    ]);

    $subscriber = User::factory()->createOne();
    $subscription = $subscriber->subscribeTo($plan);

    Event::fake();

    $subscription->useUnitsOnFeature($feature, $usage);

    Event::assertDispatched(FeatureUsed::class);

    $this->assertDatabaseHas('feature_subscription', [
      'feature_id' => $feature->id,
      'subscription_id' => $subscription->id,
      'used' => $usage,
      'active' => true,
      'ends_at' => $feature->calculateNextRecurrenceEnd($subscription->starts_at),
    ]);
  }

  public function test_model_cant_use_an_inactive_feature()
  {
    $units = $this->faker->numberBetween(5, 10);
    $usage = $this->faker->numberBetween(1, $units);

    $plan = Plan::factory()->createOne();
    $feature = Feature::factory()->inactive()->createOne();

    $feature->plans()->attach($plan, [
      'units' => $units,
    ]);

    $subscriber = User::factory()->createOne();
    $subscription = $subscriber->subscribeTo($plan);

    Event::fake();

    $this->expectException(CannotUseFeatureException::class);
    $this->expectExceptionMessage('This feature is inactive for this plan: Only active feature can be used');

    $subscription->useUnitsOnFeature($feature, $usage);

    Event::assertNotDispatched(FeatureUsed::class);

    $this->assertDatabaseHas('feature_subscription', [
      'feature_id' => $feature->id,
      'subscription_id' => $subscription->id,
      'used' => $usage,
      'active' => false,
      'ends_at' => $feature->calculateNextRecurrenceEnd($subscription->starts_at),
    ]);
  }

  public function test_model_cant_use_missing_feature()
  {
    $units = $this->faker->numberBetween(5, 10);
    $used = $this->faker->numberBetween(1, $units);

    $plan = Plan::factory()->createOne();

    $feature = Feature::factory()->consumable()->createOne();
    $missingFeature = Feature::factory()->consumable()->createOne();

    $feature->plans()->attach($plan, [
      'units' => $units,
    ]);

    $subscriber = User::factory()->createOne();
    $subscription = $subscriber->subscribeTo($plan);

    $this->expectException(FeatureNotFoundException::class);
    $this->expectExceptionMessage('None of the plans grants access to this feature.');

    $subscription->useUnitsOnFeature($missingFeature, $used);

    $this->assertDatabaseMissing('feature_subscription', [
      'used' => $used,
      'feature_id' => $feature->id,
      'subscriber_id' => $subscriber->id,
    ]);
  }

  public function test_model_can_check_if_feature_is_usable()
  {
    $units = $this->faker->numberBetween(5, 10);
    $overflowUsage = $units + 10;

    $plan = Plan::factory()->createOne();

    $feature = Feature::factory()->consumable()->createOne();
    $missingFeature = Feature::factory()->consumable()->createOne();
    $inactiveFeature = Feature::factory()->inactive()->createOne();
    $featureWithDeactivatedUsage = Feature::factory()->createOne();

    $feature->plans()->attach($plan, [
      'units' => $units,
    ]);

    $featureWithDeactivatedUsage->plans()->attach($plan, [
      'units' => $units,
    ]);

    $subscriber = User::factory()->createOne();
    $subscription = $subscriber->subscribeTo($plan);

    $this->assertTrue($subscription->deactivateFeature($featureWithDeactivatedUsage));
    $this->assertTrue($subscription->canUseFeature($feature));
    $this->assertFalse($subscription->canUseFeature($feature, $overflowUsage));
    $this->assertFalse($subscription->canUseFeature($missingFeature));
    $this->assertFalse($subscription->canUseFeature($inactiveFeature));
    $this->assertFalse($subscription->canUseFeature($featureWithDeactivatedUsage));
  }

  public function test_model_can_check_if_feature_has_sufficient_balance()
  {
    $units = $this->faker->numberBetween(5, 10);
    $overflowUsage = $units + 10;

    $plan = Plan::factory()->createOne();

    $feature = Feature::factory()->consumable()->createOne();

    $feature->plans()->attach($plan, [
      'units' => $units,
    ]);


    $subscriber = User::factory()->createOne();
    $subscription = $subscriber->subscribeTo($plan);

    $this->assertTrue($subscription->canUseFeature($feature));
    $this->assertTrue($subscription->hasSufficientBalance($feature, $units));
    $this->assertFalse($subscription->hasSufficientBalance($feature, $overflowUsage));
  }

  public function test_model_can_get_remaining_units_for_feature()
  {
    $units = $this->faker->numberBetween(5, 10);
    $usage = $units - 2;
    $remainingUnits = $units - $usage;

    $plan = Plan::factory()->createOne();

    $feature = Feature::factory()->consumable()->createOne();

    $feature->plans()->attach($plan, [
      'units' => $units,
    ]);

    $subscriber = User::factory()->createOne();
    $subscription = $subscriber->subscribeTo($plan);

    $this->assertEquals($units, $subscription->getRemainingUnitsForFeature($feature));


    $subscription->useUnitsOnFeature($feature, $usage);

    $this->assertEquals($remainingUnits, $subscription->getRemainingUnitsForFeature($feature));
  }

  public function test_model_can_get_max_units_for_feature()
  {
    $units = $this->faker->numberBetween(5, 10);

    $plan = Plan::factory()->createOne();

    $feature = Feature::factory()->consumable()->createOne();

    $feature->plans()->attach($plan, [
      'units' => $units,
    ]);

    $subscriber = User::factory()->createOne();
    $subscription = $subscriber->subscribeTo($plan);

    $this->assertEquals($units, $subscription->getMaxFeatureUnits($feature));
  }

  public function test_model_can_get_used_units_for_feature()
  {
    $units = $this->faker->numberBetween(5, 10);
    $usage = $units - 2;

    $plan = Plan::factory()->createOne();

    $feature = Feature::factory()->consumable()->createOne();

    $feature->plans()->attach($plan, [
      'units' => $units,
    ]);

    $subscriber = User::factory()->createOne();
    $subscription = $subscriber->subscribeTo($plan);

    $this->assertEquals($units, $subscription->getRemainingUnitsForFeature($feature));

    $subscription->useUnitsOnFeature($feature, $usage);

    $this->assertEquals($usage, $subscription->getUnitsUsedForFeature($feature));
  }

  public function test_model_can_activate_or_deactivate_a_feature()
  {
    $units = $this->faker->numberBetween(5, 10);
    $usage = $units - 2;

    $plan = Plan::factory()->createOne();

    $feature = Feature::factory()->consumable()->createOne();

    $feature->plans()->attach($plan, [
      'units' => $units,
    ]);

    $subscriber = User::factory()->createOne();
    $subscription = $subscriber->subscribeTo($plan);

    $this->assertEquals($units, $subscription->getRemainingUnitsForFeature($feature));
    $this->assertTrue($subscription->deactivateFeature($feature));

    $this->expectException(CannotUseFeatureException::class);
    $this->expectExceptionMessage('The use of this feature has been deactivated for this subscription');

    $subscription->useUnitsOnFeature($feature, $usage);

    $this->assertEquals(0, $subscription->getUnitsUsedForFeature($feature));

    $this->assertTrue($subscription->activateFeature($feature));

    $subscription->useUnitsOnFeature($feature, $usage);

    $this->assertEquals($usage, $subscription->getUnitsUsedForFeature($feature));
  }
}
