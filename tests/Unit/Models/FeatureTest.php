<?php

namespace Jojostx\Larasubs\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Carbon;
use Jojostx\Larasubs\Enums\IntervalType;
use Jojostx\Larasubs\Models\Feature;
use Jojostx\Larasubs\Tests\TestCase;

class FeatureTest extends TestCase
{
  use RefreshDatabase;
  use WithFaker;

  public function test_model_calculates_yearly_expiration()
  {
    Carbon::setTestNow(now());

    $years = $this->faker->randomDigitNotNull();

    $feature = Feature::factory()->create([
      'interval' => $years,
      'interval_type' => IntervalType::YEAR,
    ]);

    $this->assertEquals(now()->addYears($years), $feature->calculateNextRecurrenceEnd());
  }

  public function test_model_calculates_monthly_expiration()
  {
    Carbon::setTestNow(now());

    $months = $this->faker->randomDigitNotNull();

    $feature = Feature::factory()->create([
      'interval' => $months,
      'interval_type' =>  IntervalType::MONTH,
    ]);

    $this->assertEquals(now()->addMonths($months), $feature->calculateNextRecurrenceEnd());
  }

  public function test_model_calculates_weekly_expiration()
  {
    Carbon::setTestNow(now());

    $weeks = $this->faker->randomDigitNotNull();
    $feature = Feature::factory()->create([
      'interval_type' => IntervalType::WEEK,
      'interval' => $weeks,
    ]);

    $this->assertEquals(now()->addWeeks($weeks), $feature->calculateNextRecurrenceEnd());
  }

  public function test_model_calculates_daily_expiration()
  {
    Carbon::setTestNow(now());

    $days = $this->faker->randomDigitNotNull();
    $feature = Feature::factory()->create([
      'interval_type' => IntervalType::DAY,
      'interval' => $days,
    ]);

    $this->assertEquals(now()->addDays($days), $feature->calculateNextRecurrenceEnd());
  }

  public function test_model_calculates_next_recurrence_end_considering_recurrences()
  {
    Carbon::setTestNow(now());

    $feature = Feature::factory()->create([
      'interval' => 2,
      'interval_type' => IntervalType::WEEK
    ]);

    $startDate = now()->subDays(11);

    $this->assertEquals(now()->addDays(3), $feature->calculateNextRecurrenceEnd($startDate));
  }

  public function test_model_is_not_consumable_by_default()
  {
    $creationPayload = Feature::factory()->raw();

    unset($creationPayload['consumable']);

    $feature = Feature::create($creationPayload);

    $this->assertDatabaseHas('features', [
      'id' => $feature->id,
      'consumable' => false,
    ]);
  }

  public function test_model_is_sortable()
  {
    $newOrder = [2, 1];

    Feature::factory(2)->create();
    Feature::setNewOrder($newOrder);
    $orderFeatures = Feature::ordered()->pluck('id');

    $this->assertEquals($newOrder, $orderFeatures->toArray());
  }

  public function test_model_has_slug()
  {
    $feature = Feature::factory()->create(['name' => 'test feature']);

    $this->assertEquals('test-feature', $feature->slug);
  }

  public function test_model_has_translatable_slug()
  {
    $feature = Feature::factory()->create(['name' => 'Name in English']);
    $feature
      ->setTranslation('name', 'nl', 'Naam in het Nederlands')
      ->save();

    $this->assertEquals('naam-in-het-nederlands', $feature->getTranslation('slug', 'nl'));
  }
}
