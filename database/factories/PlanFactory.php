<?php

namespace Jojostx\Larasubs\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Jojostx\Larasubs\Enums\IntervalType;
use Jojostx\Larasubs\Models\Plan;

class PlanFactory extends Factory
{
  protected $model = Plan::class;

  /**
   * Define the model's default state.
   *
   * @return array
   */
  public function definition()
  {
    $interval_type = $this->faker->randomElement([
      IntervalType::YEAR,
      IntervalType::MONTH,
      IntervalType::WEEK,
      IntervalType::DAY
    ]);

    return [
      'name'             => $this->faker->words(asText: true),
      'active'             => true,
      'price' => 1000,
      'currency' => 'NGN',
      'interval'      => $this->faker->randomDigitNotNull(),
      'interval_type' => $interval_type,
      'grace_interval'       => $this->faker->randomDigitNotNull(),
      'grace_interval_type' => $interval_type,
      'trial_interval'       => $this->faker->randomDigitNotNull(),
      'trial_interval_type' => $interval_type,
    ];
  }

  public function withGracePeriod()
  {
    return $this->state([
      'grace_interval'       => $this->faker->randomDigitNotNull(),
      'grace_interval_type' => $this->faker->randomElement([
        IntervalType::YEAR,
        IntervalType::MONTH,
        IntervalType::WEEK,
        IntervalType::DAY
      ]),
    ]);
  }

  public function withTrialPeriod()
  {
    return $this->state([
      'trial_interval'       => $this->faker->randomDigitNotNull(),
      'trial_interval_type' => $this->faker->randomElement([
        IntervalType::DAY
      ]),
    ]);
  }

  public function active()
  {
    return $this->state([
      'active'       => true,
    ]);
  }

  public function inactive()
  {
    return $this->state([
      'active'       => false,
    ]);
  }
}
