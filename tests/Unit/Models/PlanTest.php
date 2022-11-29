<?php

namespace Jojostx\Larasubs\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Carbon;
use Jojostx\Larasubs\Enums\IntervalType;
use Jojostx\Larasubs\Models\Feature;
use Jojostx\Larasubs\Models\Plan;
use Jojostx\Larasubs\Models\Subscription;
use Jojostx\Larasubs\Tests\TestCase;

class PlanTest extends TestCase
{
  use RefreshDatabase;
  use WithFaker;

  public function test_model_calculates_yearly_expiration()
  {
    Carbon::setTestNow(now());

    $years = $this->faker->randomDigitNotNull();

    $plan = Plan::factory()->create([
      'interval' => $years,
      'interval_type' => IntervalType::YEAR,
    ]);

    $this->assertEquals(now()->addYears($years), $plan->calculateNextRecurrenceEnd());
  }

  public function test_model_calculates_monthly_expiration()
  {
    Carbon::setTestNow(now());

    $months = $this->faker->randomDigitNotNull();

    $plan = Plan::factory()->create([
      'interval' => $months,
      'interval_type' =>  IntervalType::MONTH,
    ]);

    $this->assertEquals(now()->addMonths($months), $plan->calculateNextRecurrenceEnd());
  }

  public function test_model_calculates_weekly_expiration()
  {
    Carbon::setTestNow(now());

    $weeks = $this->faker->randomDigitNotNull();
    $plan = Plan::factory()->create([
      'interval_type' => IntervalType::WEEK,
      'interval' => $weeks,
    ]);

    $this->assertEquals(now()->addWeeks($weeks), $plan->calculateNextRecurrenceEnd());
  }

  public function test_model_calculates_daily_expiration()
  {
    Carbon::setTestNow(now());

    $days = $this->faker->randomDigitNotNull();
    $plan = Plan::factory()->create([
      'interval_type' => IntervalType::DAY,
      'interval' => $days,
    ]);

    $this->assertEquals(now()->addDays($days), $plan->calculateNextRecurrenceEnd());
  }

  public function test_model_calculates_next_recurrence_end_considering_recurrences()
  {
    Carbon::setTestNow(now());

    $plan = Plan::factory()->create([
      'interval' => 2,
      'interval_type' => IntervalType::WEEK
    ]);

    $startDate = now()->subDays(11);

    $this->assertEquals(now()->addDays(3), $plan->calculateNextRecurrenceEnd($startDate));
  }

  public function test_model_can_calculate_grace_interval_end()
  {
    Carbon::setTestNow(now());

    $days = $this->faker->randomDigitNotNull();
    $graceDays = $this->faker->randomDigitNotNull();

    $plan = Plan::factory()->create([
      'grace_interval' => $graceDays,
      'grace_interval_type' => IntervalType::DAY,
      'interval' => $days,
      'interval_type' => IntervalType::DAY,
    ]);

    $this->assertEquals(
      now()->addDays($days)->addDays($graceDays),
      $plan->calculateGracePeriodEnd($plan->calculateNextRecurrenceEnd()),
    );
  }

  public function test_model_can_calculate_trial_interval_end()
  {
    Carbon::setTestNow(now());

    $days = $this->faker->randomDigitNotNull();
    $trialDays = $this->faker->randomDigitNotNull();

    $plan = Plan::factory()->create([
      'trial_interval' => $trialDays,
      'trial_interval_type' => IntervalType::DAY,
      'interval' => $days,
      'interval_type' => IntervalType::DAY,
    ]);

    $this->assertEquals(
      now()->addDays($days)->addDays($trialDays),
      $plan->calculateTrialPeriodEnd($plan->calculateNextRecurrenceEnd()),
    );
  }

  public function test_model_is_active_by_default()
  {
    $creationPayload = Plan::factory()->raw();

    unset($creationPayload['active']);

    $plan = Plan::create($creationPayload);

    $this->assertDatabaseHas('plans', [
      'id' => $plan->id,
      'active' => true,
    ]);
  }

  public function test_model_is_sortable()
  {
    $newOrder = [2, 1];

    Plan::factory(2)->create();
    Plan::setNewOrder($newOrder);
    $orderPlans = Plan::ordered()->pluck('id');

    $this->assertEquals($newOrder, $orderPlans->toArray());
  }

  public function test_model_has_slug()
  {
    $plan = Plan::factory()->create(['name' => 'test plan']);

    $this->assertEquals('test-plan', $plan->slug);
  }

  public function test_model_has_grace()
  {
    Carbon::setTestNow(now());

    $graceDays = $this->faker->randomDigitNotNull();

    $plan = Plan::factory()->create([
      'grace_interval' => $graceDays,
      'grace_interval_type' => IntervalType::DAY,
    ]);

    $this->assertTrue($plan->hasGracePeriod());
  }

  public function test_model_has_trial_period()
  {
    Carbon::setTestNow(now());

    $trialDays = $this->faker->randomDigitNotNull();

    $plan = Plan::factory()->create([
      'trial_interval' => $trialDays,
      'trial_interval_type' => IntervalType::DAY,
    ]);

    $this->assertTrue($plan->hasTrialPeriod());
  }

  public function test_model_can_be_activate()
  {
    Carbon::setTestNow(now());

    $plan = Plan::factory()->create([
      'active' => false,
    ]);

    $this->assertTrue($plan->isInactive());

    $plan->activate();

    $this->assertTrue($plan->isActive());
  }

  public function test_model_can_be_deactivate()
  {
    Carbon::setTestNow(now());

    $plan = Plan::factory()->create([
      'active' => true,
    ]);

    $this->assertTrue($plan->isActive());

    $plan->deactivate();

    $this->assertTrue($plan->isInactive());
  }

  public function test_model_is_free()
  {
    Carbon::setTestNow(now());

    $plan = Plan::factory()->create();

    $this->assertFalse($plan->isFree());

    $plan->update(['price' => 0]);

    $this->assertTrue($plan->isFree());
  }

  public function test_model_can_retrieve_features()
  {
    $features = Feature::factory()
      ->count($featuresCount = $this->faker->randomDigitNotNull())
      ->create();

    $plan = Plan::factory()
      ->hasAttached($features)
      ->create();

    $this->assertEquals($featuresCount, $plan->features()->count());

    $features->each(function ($feature) use ($plan) {
      $this->assertTrue($plan->features->contains($feature));
    });
  }

  public function test_model_can_retrieve_features_with_units()
  {
    $features = Feature::factory()
      ->count($featuresCount = $this->faker->randomDigitNotNull())
      ->create();

    $plan = Plan::factory()
      ->hasAttached($features, ['units' => 5])
      ->create();

    $this->assertEquals($featuresCount, $plan->features()->count());

    $plan->features->each(function ($feature) {
      $this->assertEquals(5, $feature->pivot->units);
    });
  }

  public function test_model_can_retrieve_subscriptions()
  {
    $plan = Plan::factory()
      ->create();

    $subscriptions = Subscription::factory()
      ->for($plan)
      ->count($subscriptionsCount = $this->faker->randomDigitNotNull())
      ->started()
      ->notEnded()
      ->notCancelled()
      ->create();

    $this->assertEquals($subscriptionsCount, $plan->subscriptions()->count());
    $subscriptions->each(function ($subscription) use ($plan) {
      $this->assertTrue($plan->subscriptions->contains($subscription));
    });
  }
}
