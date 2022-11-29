<?php

namespace Jojostx\Larasubs\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Jojostx\Larasubs\Models\Feature;
use Jojostx\Larasubs\Models\FeatureSubscription;
use Jojostx\Larasubs\Models\Subscription;

class FeatureSubscriptionFactory extends Factory
{
    protected $model = FeatureSubscription::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'feature_id'      => Feature::factory(),
            'subscription_id'   => Subscription::factory(),
            'used'     => $this->faker->randomDigitNotNull(),
            'ends_at'      =>  now(),
        ];
    }

    public function ended()
    {
        return $this->state(fn (array $attributes) => [
            'ends_at' => now(),
        ]);
    }

    public function notEnded()
    {
        return $this->state(fn (array $attributes) => [
            'ends_at' => now()->addDays($this->faker->randomDigitNotNull()),
        ]);
    }
}