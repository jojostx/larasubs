<?php

namespace Jojostx\Larasubs\Tests\Unit\Concerns;

use LucasDotVin\DBQueriesCounter\Traits\CountsQueries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
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

  public function testModelCachesFeatures()
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

  public function testModelCanRetrieveFeatureBySlug()
  {
    $units = $this->faker->numberBetween(5, 10);

    $plan = Plan::factory()->createOne();
    $feature = Feature::factory()->consumable()->createOne();
    $feature->plans()->attach($plan, [
      'units' => $units,
    ]);

    $subscriber = User::factory()->createOne();
    $subscription = $subscriber->subscribeTo($plan);

    $returned_feature = $subscription->getFeatureBySlug($feature);

    $this->assertEquals($returned_feature->getKey(), $feature->getKey());
  }

  public function testModelCanRetrieveFeatureById()
  {
    $units = $this->faker->numberBetween(5, 10);

    $plan = Plan::factory()->createOne();
    $feature = Feature::factory()->consumable()->createOne();
    $feature->plans()->attach($plan, [
      'units' => $units,
    ]);

    $subscriber = User::factory()->createOne();
    $subscription = $subscriber->subscribeTo($plan);

    $returned_feature = $subscription->getFeatureById($feature->getKey());

    $this->assertEquals($returned_feature->getKey(), $feature->getKey());
  }

  // public function testModelCanGetUsageByFeature()
  // {
  //   $units = $this->faker->numberBetween(5, 10);
  //   $usage = $this->faker->numberBetween(1, $units);

  //   $plan = Plan::factory()->createOne();
  //   $feature = Feature::factory()->consumable()->createOne();
  //   $feature->plans()->attach($plan, [
  //     'units' => $units,
  //   ]);

  //   $subscriber = User::factory()->createOne();
  //   $subscription = $subscriber->subscribeTo($plan);

  //   $subscription->firstOrCreateUsage($feature, $usage);

  //   $this->assertDatabaseHas('feature_usages', [
  //     'feature_id' => $feature->id,
  //     'subscription_id' => $subscription->id,
  //     'used' => $usage,
  //     'ends_at' => $feature->calculateNextRecurrenceEnd($subscription->starts_at),
  //   ]);
  // }

  public function testModelCanRetrieveOrCreateUsage()
  {
    $units = $this->faker->numberBetween(5, 10);

    $plan = Plan::factory()->createOne();
    $feature = Feature::factory()->consumable()->createOne();
    $feature->plans()->attach($plan, [
      'units' => $units,
    ]);

    $subscriber = User::factory()->createOne();
    $subscription = $subscriber->subscribeTo($plan);

    $f_subscription = $subscription->getUsageByFeature($feature);

    $this->assertDatabaseHas('feature_subscription', [
      'feature_id' => $feature->id,
      'subscription_id' => $subscription->id,
      'used' => 0,
      'ends_at' => $subscription->ends_at,
    ]);

    $this->assertDatabaseHas('feature_subscription', [
      'feature_id' => $f_subscription->feature->id,
      'subscription_id' => $f_subscription->subscription->id,
      'used' => $f_subscription->used,
      'ends_at' => $subscription->ends_at,
    ]);
  }

  // public function testModelCanUseAFeature()
  // {
  //   $units = $this->faker->numberBetween(5, 10);
  //   $usage = $this->faker->numberBetween(1, $units);

  //   $plan = Plan::factory()->createOne();
  //   $feature = Feature::factory()->consumable()->createOne();
  //   $feature->plans()->attach($plan, [
  //     'units' => $units,
  //   ]);

  //   $subscriber = User::factory()->createOne();
  //   $subscription = $subscriber->subscribeTo($plan);

  //   Event::fake();

  //   $subscription->useUnitsOnFeature($feature, $usage);

  //   Event::assertDispatched(FeatureUsed::class);

  //   $this->assertDatabaseHas('feature_usages', [
  //     'feature_id' => $feature->id,
  //     'subscription_id' => $subscription->id,
  //     'used' => $usage,
  //     'ends_at' => $feature->calculateNextRecurrenceEnd($subscription->starts_at),
  //   ]);
  // }

  // public function testModelCanUseANotConsumableFeatureIfItIsAvailable()
  // {
  //   $plan = Plan::factory()->createOne();
  //   $feature = Feature::factory()->notConsumable()->createOne();
  //   $feature->plans()->attach($plan);

  //   $subscriber = User::factory()->createOne();
  //   $subscriber->subscribeTo($plan);

  //   $subscriber->consume($feature->name);

  //   $this->assertDatabaseHas('feature_usages', [
  //     'used' => null,
  //     'feature_id' => $feature->id,
  //     'subscriber_id' => $subscriber->id,
  //   ]);
  // }

  // public function testModelCantUseAnUnavailableFeature()
  // {
  //   $units = $this->faker->numberBetween(5, 10);
  //   $usage = $this->faker->numberBetween(1, $units);

  //   $plan = Plan::factory()->createOne();
  //   $feature = Feature::factory()->consumable()->createOne();
  //   $feature->plans()->attach($plan, [
  //     'units' => $units,
  //   ]);

  //   $subscriber = User::factory()->createOne();
  //   $subscriber->subscribeTo($plan, now()->subDay());

  //   $this->expectException(OutOfBoundsException::class);
  //   $this->expectExceptionMessage('None of the active plans grants access to this feature.');

  //   $subscriber->consume($feature->name, $usage);

  //   $this->assertDatabaseMissing('feature_usages', [
  //     'used' => $usage,
  //     'feature_id' => $feature->id,
  //     'subscriber_id' => $subscriber->id,
  //   ]);
  // }

  // public function testModelCantUseAFeatureBeyondItsUnits()
  // {
  //   $units = $this->faker->numberBetween(5, 10);
  //   $usage = $units + 1;

  //   $plan = Plan::factory()->createOne();
  //   $feature = Feature::factory()->consumable()->createOne();
  //   $feature->plans()->attach($plan, [
  //     'units' => $units,
  //   ]);

  //   $subscriber = User::factory()->createOne();
  //   $subscriber->subscribeTo($plan);

  //   $this->expectException(OverflowException::class);
  //   $this->expectExceptionMessage('The feature has no enough units to this usage.');

  //   $subscriber->consume($feature->name, $usage);

  //   $this->assertDatabaseMissing('feature_usages', [
  //     'used' => $usage,
  //     'feature_id' => $feature->id,
  //     'subscriber_id' => $subscriber->id,
  //   ]);
  // }

  // public function testModelCanUseSomeAmountOfAConsumableFeature()
  // {
  //   $units = $this->faker->numberBetween(5, 10);
  //   $usage = $this->faker->numberBetween(1, $units);

  //   $plan = Plan::factory()->createOne();
  //   $feature = Feature::factory()->consumable()->createOne();
  //   $feature->plans()->attach($plan, [
  //     'units' => $units,
  //   ]);

  //   $subscriber = User::factory()->createOne();
  //   $subscriber->subscribeTo($plan);

  //   $modelCanUse = $subscriber->canUse($feature->name, $usage);

  //   $this->assertTrue($modelCanUse);
  // }

  // public function testModelCantUseSomeAmountOfAConsumableFeature()
  // {
  //   $units = $this->faker->numberBetween(5, 10);
  //   $usage = $units + 1;

  //   $plan = Plan::factory()->createOne();
  //   $feature = Feature::factory()->consumable()->createOne();
  //   $feature->plans()->attach($plan, [
  //     'units' => $units,
  //   ]);

  //   $subscriber = User::factory()->createOne();
  //   $subscriber->subscribeTo($plan);

  //   $modelCanUse = $subscriber->canUse($feature->name, $usage);

  //   $this->assertFalse($modelCanUse);
  // }

  // public function testModelCanRetrieveTotalUsagesForAFeature()
  // {
  //   $usage = $this->faker->randomDigitNotNull();

  //   $plan = Plan::factory()->createOne();
  //   $feature = Feature::factory()->consumable()->createOne();
  //   $feature->plans()->attach($plan);

  //   $subscriber = User::factory()->createOne();
  //   $subscriber->subscribeTo($plan);
  //   $subscriber->featureUsages()
  //     ->make([
  //       'used' => $usage,
  //       'ends_at' => now()->addDay(),
  //     ])
  //     ->feature()
  //     ->associate($feature)
  //     ->save();

  //   config()->set('soulbscription.feature_tickets', true);

  //   $receivedUsage = $subscriber->getCurrentUsage($feature->name);

  //   $this->assertEquals($usage, $receivedUsage);
  // }

  // public function testModelCanRetrieveRemainingUnitsForAFeature()
  // {
  //   $units = $this->faker->numberBetween(6, 10);
  //   $usage = $this->faker->numberBetween(1, 5);

  //   $plan = Plan::factory()->createOne();
  //   $feature = Feature::factory()->consumable()->createOne();
  //   $feature->plans()->attach($plan, [
  //     'units' => $units,
  //   ]);

  //   $subscriber = User::factory()->createOne();
  //   $subscriber->subscribeTo($plan);
  //   $subscriber->featureUsages()
  //     ->make([
  //       'used' => $usage,
  //       'ends_at' => now()->addDay(),
  //     ])
  //     ->feature()
  //     ->associate($feature)
  //     ->save();

  //   config()->set('soulbscription.feature_tickets', true);

  //   $receivedRemainingUnits = $subscriber->getRemainingUnits($feature->name);

  //   $this->assertEquals($units - $usage, $receivedRemainingUnits);
  // }

  // public function testItDoesNotReturnNegativeUnitsForFeatures()
  // {
  //   $units = $this->faker->numberBetween(5, 10);
  //   $usage = $this->faker->numberBetween($units + 1, $units * 2);

  //   $plan = Plan::factory()->createOne();
  //   $feature = Feature::factory()->postpaid()->createOne();
  //   $feature->plans()->attach($plan, [
  //     'units' => $units,
  //   ]);

  //   $subscriber = User::factory()->createOne();
  //   $subscriber->subscribeTo($plan);

  //   $subscriber->consume($feature->name, $usage);

  //   $this->assertEquals(0, $subscriber->getRemainingUnits($feature->name));
  // }

  // public function testItCanSetQuotaFeatureUsage()
  // {
  //   $units = $this->faker->numberBetween(5, 10);
  //   $usage = $this->faker->numberBetween(1, $units / 2);

  //   $plan = Plan::factory()->createOne();
  //   $feature = Feature::factory()->quota()->createOne();
  //   $feature->plans()->attach($plan, [
  //     'units' => $units,
  //   ]);

  //   $subscriber = User::factory()->createOne();
  //   $subscriber->subscribeTo($plan);

  //   $subscriber->consume($feature->name, $usage);
  //   $subscriber->consume($feature->name, $usage);
  //   $subscriber->setUsedQuota($feature->name, $usage);

  //   $this->assertDatabaseHas('feature_usages', [
  //     'used' => $usage,
  //     'feature_id' => $feature->id,
  //     'subscriber_id' => $subscriber->id,
  //     'ends_at' => null,
  //   ]);
  // }

  // public function testItCreateANotExpirableUsageForQuotaFeatures()
  // {
  //   $units = $this->faker->numberBetween(5, 10);
  //   $usage = $this->faker->numberBetween(1, $units);

  //   $plan = Plan::factory()->createOne();
  //   $feature = Feature::factory()->quota()->createOne();
  //   $feature->plans()->attach($plan, [
  //     'units' => $units,
  //   ]);

  //   $subscriber = User::factory()->createOne();
  //   $subscriber->subscribeTo($plan);

  //   $subscriber->consume($feature->name, $usage);

  //   $this->assertDatabaseHas('feature_usages', [
  //     'used' => $usage,
  //     'feature_id' => $feature->id,
  //     'subscriber_id' => $subscriber->id,
  //     'ends_at' => null,
  //   ]);
  // }
}
