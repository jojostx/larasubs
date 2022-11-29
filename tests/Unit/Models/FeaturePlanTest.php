<?php

namespace Jojostx\Larasubs\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Jojostx\Larasubs\Models\Feature;
use Jojostx\Larasubs\Models\FeaturePlan;
use Jojostx\Larasubs\Models\Plan;
use Jojostx\Larasubs\Tests\TestCase;

class FeaturePlanTest extends TestCase
{
  use RefreshDatabase;
  use WithFaker;

  public function test_model_can_retrieve_plan()
  {
    $feature = Feature::factory()
      ->create();

    $plan = Plan::factory()->create();
    $plan->features()->attach($feature);

    $featurePlanPivot = FeaturePlan::first();

    $this->assertEquals($plan->id, $featurePlanPivot->plan->id);
  }

  public function test_model_can_retrieve_feature()
  {
    $feature = Feature::factory()
      ->create();

    $plan = Plan::factory()->create();
    $plan->features()->attach($feature);

    $featurePlanPivot = FeaturePlan::first();

    $this->assertEquals($feature->id, $featurePlanPivot->feature->id);
  }

  public function test_model_has_units()
  {
    $feature = Feature::factory()
      ->create();

    $plan = Plan::factory()->create();
    $plan->features()->attach($feature);

    $featurePlanPivot = FeaturePlan::first();
    $featurePlanPivot->update(['units' => 5]);

    $this->assertEquals($feature->id, $featurePlanPivot->feature->id);
    $this->assertEquals(5, $featurePlanPivot->units);
  }
}
