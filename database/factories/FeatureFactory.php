<?php

namespace Jojostx\Larasubs\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Jojostx\Larasubs\Enums\IntervalType;
use Jojostx\Larasubs\Models\Feature;

class FeatureFactory extends Factory
{
  protected $model = Feature::class;

  /**
   * Define the model's default state.
   *
   * @return array
   */
  public function definition()
  {
    return [
      'name'             => $this->faker->words(asText: true),
      'consumable'       => $this->faker->boolean(),
      'interval'      => $this->faker->randomDigitNotNull(),
      'interval_type' => $this->faker->randomElement([
        IntervalType::YEAR,
        IntervalType::MONTH,
        IntervalType::WEEK,
        IntervalType::DAY
      ]),
    ];
  }

  public function consumable()
  {
    return $this->state(fn (array $attributes) => [
      'consumable' => true,
    ]);
  }

  public function notConsumable()
  {
    return $this->state(fn (array $attributes) => [
      'consumable' => false,
      'interval' => null,
      'interval_type' => null,
    ]);
  }
}
