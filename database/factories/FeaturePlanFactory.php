<?php

namespace Jojostx\Larasubs\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Jojostx\Larasubs\Models\Feature;
use Jojostx\Larasubs\Models\FeaturePlan;
use Jojostx\Larasubs\Models\Plan;

class FeaturePlanFactory extends Factory
{
    protected $model = FeaturePlan::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'feature_id' => Feature::factory(),
            'plan_id' => Plan::factory(),
            'units' => $this->faker->randomDigitNotNull(),
        ];
    }
}
