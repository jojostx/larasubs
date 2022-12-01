<?php

namespace Jojostx\Larasubs\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Jojostx\Larasubs\Models\Plan;
use Jojostx\Larasubs\Models\Subscription;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $date = now();
        $ends_at = now()->addMonths(6);

        return [
            'name' => $this->faker->words(asText: true),
            'subscribable_id' => $this->faker->randomNumber(),
            'subscribable_type' => $this->faker->word(),
            'plan_id' => Plan::factory(),
            'trial_ends_at' => $date,
            'starts_at' => $date,
            'ends_at' => $ends_at,
            'grace_ends_at' => $ends_at->addMonth(),
            'cancels_at' => null,
        ];
    }

    public function endsWithGracePeriod()
    {
        $ends_at = now();

        return $this->state(fn (array $attributes) => [
            'ends_at' => $ends_at,
            'grace_ends_at' => $ends_at->addDays(5),
        ]);
    }

    public function ended()
    {
        return $this->state(fn (array $attributes) => [
            'ends_at' => now()->subDays(2),
        ]);
    }

    public function notEnded()
    {
        return $this->state(fn (array $attributes) => [
            'ends_at' => now()->addDays($this->faker->randomDigitNotNull()),
        ]);
    }

    public function overdue()
    {
        $ends_at = now()->subDay();

        return $this->state(fn (array $attributes) => [
            'ends_at' => $ends_at,
            'grace_ends_at' => $ends_at,
        ]);
    }

    public function started()
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => $this->faker->dateTime(),
        ]);
    }

    public function notStarted()
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => $this->faker->dateTimeBetween('now', '+10 years'),
        ]);
    }

    public function cancelled()
    {
        return $this->state(fn (array $attributes) => [
            'cancels_at' => $this->faker->dateTime(),
        ]);
    }

    public function notCancelled()
    {
        return $this->state(fn (array $attributes) => [
            'cancels_at' => null,
        ]);
    }

    public function cancelledImmediately()
    {
        $date = $this->faker->dateTime();

        return $this->state(fn (array $attributes) => [
            'ends_at' => $attributes['ends_at'] ?? $date,
            'cancels_at' => $attributes['ends_at'] ?? $date,
        ]);
    }

    public function notCancelledImmediately()
    {
        return $this->state(fn (array $attributes) => [
            'cancels_at' => null,
        ]);
    }
}
