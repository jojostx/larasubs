<?php

namespace Jojostx\Larasubs\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Jojostx\Larasubs\Events\SubscriptionCancelled;
use Jojostx\Larasubs\Events\SubscriptionPlanChanged;
use Jojostx\Larasubs\Events\SubscriptionRenewed;
use Jojostx\Larasubs\Events\SubscriptionScheduled;
use Jojostx\Larasubs\Events\SubscriptionStarted;
use Jojostx\Larasubs\Models\Feature;
use Jojostx\Larasubs\Models\Plan;
use Jojostx\Larasubs\Models\Subscription;
use Jojostx\Larasubs\Tests\Fixtures\Models\User;
use Jojostx\Larasubs\Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    public function test_model_can_retrieve_plan()
    {
        $plan = Plan::factory()
            ->create();

        $subscriptions = Subscription::factory()
            ->count($subscriptionsCount = $this->faker->randomDigitNotNull())
            ->for($plan)
            ->create();

        $this->assertEquals($subscriptionsCount, $plan->subscriptions()->count());

        $subscriptions->each(function ($subscription) use ($plan) {
            $this->assertEquals($plan->getKey(), $subscription->plan->getKey());
        });
    }

    public function test_model_can_retrieve_subcriber()
    {
        $plan = Plan::factory()
            ->create();

        $subscriber = User::factory()->create();

        $subscriptions = Subscription::factory()
            ->count($this->faker->randomDigitNotNull())
            ->for($plan)
            ->for($subscriber, 'subscriber')
            ->create();

        $subscriptions->each(function ($subscription) use ($plan, $subscriber) {
            $this->assertEquals($plan->getKey(), $subscription->plan->getKey());
            $this->assertEquals($subscription->subscriber->getKey(), $subscriber->getKey());
        });
    }

    public function test_model_can_retrieve_features()
    {
        $features = Feature::factory()
            ->count($featuresCount = $this->faker->randomDigitNotNull())
            ->create();

        $plan = Plan::factory()
            ->hasAttached($features)
            ->create();

        $subscriber = User::factory()->create();

        $subscriptions = Subscription::factory()
            ->count($this->faker->randomDigitNotNull())
            ->for($plan)
            ->for($subscriber, 'subscriber')
            ->create();

        $subscriptions->each(function ($subscription) use ($featuresCount) {
            // check if plan features count is equal to the features count for the sub
            $this->assertEquals($subscription->features->count(), $featuresCount);
        });
    }

    public function test_model_plan_can_be_changed()
    {
        Carbon::setTestNow(now());

        $old_plan = Plan::factory()->create();

        $new_plan = Plan::factory()->create();

        $subscriber = User::factory()->create();

        $subscription = Subscription::factory()
            ->for($old_plan)
            ->for($subscriber, 'subscriber')
            ->create([
                'ends_at' => now()->subDays(1),
            ]);

        Event::fake();

        $this->assertDatabaseHas('subscriptions', [
            'plan_id' => $old_plan->id,
            'subscribable_id' => $subscriber->id,
            'subscribable_type' => User::class,
        ]);

        $subscription->changePlan($new_plan);

        Event::assertDispatched(SubscriptionPlanChanged::class);

        $this->assertDatabaseHas('subscriptions', [
            'plan_id' => $new_plan->id,
            'subscribable_id' => $subscriber->id,
            'subscribable_type' => User::class,
        ]);
    }

    public function test_model_renews()
    {
        Carbon::setTestNow(now());

        $plan = Plan::factory()->create();
        $subscriber = User::factory()->create();
        $subscription = Subscription::factory()
            ->for($plan)
            ->for($subscriber, 'subscriber')
            ->create([
                'ends_at' => now()->subDays(1),
            ]);

        $expectedEndedAt = $plan->calculateNextRecurrenceEnd($subscription->ends_at)->toDateTimeString();

        Event::fake();

        $subscription->renew();

        Event::assertDispatched(SubscriptionRenewed::class);

        $this->assertDatabaseHas('subscriptions', [
            'plan_id' => $plan->id,
            'subscribable_id' => $subscriber->id,
            'subscribable_type' => User::class,
            'ends_at' => $expectedEndedAt,
        ]);
    }

    public function test_model_renews_based_on_current_date_if_overdue()
    {
        Carbon::setTestNow(now());

        $plan = Plan::factory()->create();
        $subscriber = User::factory()->create();
        $subscription = Subscription::factory()
            ->for($plan)
            ->for($subscriber, 'subscriber')
            ->create([
                'ends_at' => now()->subDays(2),
            ]);

        $expectedEndedAt = $plan->calculateNextRecurrenceEnd($subscription->ends_at)->toDateTimeString();

        Event::fake();

        $subscription->renew();

        Event::assertDispatched(SubscriptionRenewed::class);

        $this->assertDatabaseHas('subscriptions', [
            'plan_id' => $plan->id,
            'subscribable_id' => $subscriber->id,
            'subscribable_type' => User::class,
            'ends_at' => $expectedEndedAt,
        ]);
    }

    public function test_model_can_start()
    {
        Carbon::setTestNow(now());

        $plan = Plan::factory()->create();
        $subscriber = User::factory()->create();
        $subscription = Subscription::factory()
            ->for($plan)
            ->for($subscriber, 'subscriber')
            ->notStarted()
            ->create();

        Event::fake();

        $subscription->start();

        Event::assertDispatched(SubscriptionStarted::class);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'starts_at' => now(),
        ]);
    }

    public function test_model_can_schedule_start()
    {
        Carbon::setTestNow(now());

        $plan = Plan::factory()->create();
        $subscriber = User::factory()->create();
        $subscription = Subscription::factory()
            ->for($plan)
            ->for($subscriber, 'subscriber')
            ->notStarted()
            ->create();

        Event::fake();

        $subscription->start(now()->addDays(2));

        Event::assertDispatched(SubscriptionScheduled::class);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'starts_at' => now()->addDays(2),
        ]);
    }

    public function test_model_can_be_cancelled()
    {
        Carbon::setTestNow(now());

        $plan = Plan::factory()->create();
        $subscriber = User::factory()->create();
        $subscription = Subscription::factory()
            ->for($plan)
            ->for($subscriber, 'subscriber')
            ->notStarted()
            ->create();

        Event::fake();

        $subscription->cancel(now());

        Event::assertDispatched(SubscriptionCancelled::class);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'cancels_at' => now(),
        ]);
    }

    public function test_model_can_be_cancelled_immediately()
    {
        Carbon::setTestNow(now());

        $plan = Plan::factory()->create();
        $subscriber = User::factory()->create();
        $subscription = Subscription::factory()
            ->for($plan)
            ->for($subscriber, 'subscriber')
            ->create();

        Event::fake();

        $subscription->cancelImmediately();

        Event::assertDispatched(SubscriptionCancelled::class);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'ends_at' => now(),
            'cancels_at' => now(),
        ]);
    }

    public function test_model_can_be_reactivated()
    {
        Carbon::setTestNow(now());

        $plan = Plan::factory()->create();
        $subscriber = User::factory()->create();
        $subscription = Subscription::factory()
            ->for($plan)
            ->for($subscriber, 'subscriber')
            ->notStarted()
            ->create();

        Event::fake();

        $subscription->cancel(now());

        Event::assertDispatched(SubscriptionCancelled::class);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'cancels_at' => now(),
        ]);

        $subscription->reactivate();

        $this->assertTrue($subscription->notCancelled());
        $this->assertNotEmpty($subscription->ends_at);
    }

    public function test_model_considers_grace_period_on_overdue()
    {
        Carbon::setTestNow(now());

        $subscriber = User::factory()->create();
        $subscription = Subscription::factory()
            ->for($subscriber, 'subscriber')
            ->create([
                'grace_ends_at' => now()->addDay(),
                'ends_at' => now()->subDay(),
            ]);

        $this->assertTrue($subscription->isOnGracePeriod());
    }

    public function test_model_where_active_scope()
    {
        Subscription::factory()
            ->count($this->faker()->randomDigitNotNull())
            ->started()
            ->notEnded()
            ->notCancelled()
            ->create();

        $allSubscriptions = Subscription::get();
        $activeSubscriptions = Subscription::whereActive()->get();

        $this->assertCount($allSubscriptions->count(), $activeSubscriptions);

        $activeSubscriptions->each(
            fn ($subscription) => $this->assertContains($subscription->id, $allSubscriptions->pluck('id'))
        );
    }

    public function test_model_where_not_active_scope()
    {
        Subscription::factory()
            ->count($this->faker()->randomDigitNotNull())
            ->started()
            ->notEnded()
            ->notCancelled()
            ->create();

        $inactiveSubscriptions = Subscription::factory()
            ->count($inactiveSubscriptionCount = $this->faker()->randomDigitNotNull())
            ->started()
            ->overdue()
            ->notCancelled()
            ->create();

        $notActiveSubscriptions = Subscription::whereNotActive()->get();

        $this->assertCount($inactiveSubscriptionCount, $notActiveSubscriptions);

        $inactiveSubscriptions->each(
            fn ($subscription) => $this->assertContains($subscription->id, $notActiveSubscriptions->pluck('id'))
        );
    }

    public function test_model_returns_not_started_subscriptions_on_not_active_scope()
    {
        Subscription::factory()
            ->count($this->faker()->randomDigitNotNull())
            ->started()
            ->notEnded()
            ->notCancelled()
            ->create();

        $notStartedSubscriptions = Subscription::factory()
            ->count($notStartedSubscriptionCount = $this->faker()->randomDigitNotNull())
            ->notEnded()
            ->notStarted()
            ->notCancelled()
            ->create();

        $notActiveSubscriptions = Subscription::whereNotActive()->get();

        $this->assertCount($notStartedSubscriptionCount, $notActiveSubscriptions);

        $notStartedSubscriptions->each(
            fn ($subscription) => $this->assertContains($subscription->id, $notActiveSubscriptions->pluck('id'))
        );
    }

    public function test_model_returns_overdue_subscriptions_on_not_active_scope()
    {
        Subscription::factory()
            ->count($this->faker()->randomDigitNotNull())
            ->started()
            ->notEnded()
            ->notCancelled()
            ->create();

        $endedSubscriptions = Subscription::factory()
            ->count($endedSubscriptionCount = $this->faker()->randomDigitNotNull())
            ->started()
            ->overdue()
            ->notCancelled()
            ->create();

        $notActiveSubscriptions = Subscription::whereNotActive()->get();

        $this->assertCount($endedSubscriptionCount, $notActiveSubscriptions);
        $endedSubscriptions->each(
            fn ($subscription) => $this->assertContains($subscription->id, $notActiveSubscriptions->pluck('id'))
        );
    }

    public function test_model_returns_cancelled_subscriptions_on_not_active_scope()
    {
        Subscription::factory()
            ->count($this->faker()->randomDigitNotNull())
            ->started()
            ->notEnded()
            ->notCancelled()
            ->create();

        $cancelledSubscription = Subscription::factory()
            ->count($cancelledSubscriptionCount = $this->faker()->randomDigitNotNull())
            ->started()
            ->notEnded()
            ->cancelled()
            ->create();

        $returnedSubscriptions = Subscription::whereNotActive()->get();

        $this->assertCount($cancelledSubscriptionCount, $returnedSubscriptions);
        $cancelledSubscription->each(
            fn ($subscription) => $this->assertContains($subscription->id, $returnedSubscriptions->pluck('id'))
        );
    }

    public function test_model_returns_only_cancelled_subscriptions_with_scope()
    {
        Subscription::factory()
            ->count($this->faker()->randomDigitNotNull())
            ->started()
            ->notEnded()
            ->notCancelled()
            ->create();

        $cancelledSubscription = Subscription::factory()
            ->count($cancelledSubscriptionCount = $this->faker()->randomDigitNotNull())
            ->started()
            ->notEnded()
            ->cancelled()
            ->create();

        $returnedSubscriptions = Subscription::whereCancelled()->get();

        $this->assertCount($cancelledSubscriptionCount, $returnedSubscriptions);
        $cancelledSubscription->each(
            fn ($subscription) => $this->assertContains($subscription->id, $returnedSubscriptions->pluck('id'))
        );
    }

    public function test_model_returns_only_not_cancelled_subscriptions_with_scope()
    {
        Subscription::factory()
            ->count($this->faker()->randomDigitNotNull())
            ->started()
            ->notEnded()
            ->notCancelled()
            ->cancelled()
            ->create();

        $notCancelledSubscription = Subscription::factory()
            ->count($notCancelledSubscriptionCount = $this->faker()->randomDigitNotNull())
            ->started()
            ->notEnded()
            ->notCancelled()
            ->notCancelled()
            ->create();

        $returnedSubscriptions = Subscription::whereNotCancelled()->get();

        $this->assertCount($notCancelledSubscriptionCount, $returnedSubscriptions);
        $notCancelledSubscription->each(
            fn ($subscription) => $this->assertContains($subscription->id, $returnedSubscriptions->pluck('id'))
        );
    }

    public function test_only_started_subscriptions_are_returned_by_scope()
    {
        $startedModelsCount = $this->faker()->randomDigitNotNull();
        $startedModels = Subscription::factory()->count($startedModelsCount)->create([
            'ends_at' => now()->addDay(),
            'starts_at' => now()->subDay(),
        ]);

        $notStartedModelsCount = $this->faker()->randomDigitNotNull();
        Subscription::factory()->count($notStartedModelsCount)->create([
            'ends_at' => now()->addDay(),
            'starts_at' => now()->addDay(),
        ]);

        $returnedSubscriptions = Subscription::whereStarted();

        $this->assertEqualsCanonicalizing(
            $startedModels->pluck('id')->toArray(),
            $returnedSubscriptions->pluck('id')->toArray(),
        );
    }

    public function test_only_not_started_models_are_returned_by_scope()
    {
        $startedModelsCount = $this->faker()->randomDigitNotNull();
        Subscription::factory()->count($startedModelsCount)->create([
            'ends_at' => now()->addDay(),
            'starts_at' => now()->subDay(),
        ]);

        $notStartedModelsCount = $this->faker()->randomDigitNotNull();
        $notStartedModels = Subscription::factory()->count($notStartedModelsCount)->create([
            'ends_at' => now()->addDay(),
            'starts_at' => now()->addDay(),
        ]);

        $returnedSubscriptions = Subscription::whereNotStarted();

        $this->assertEqualsCanonicalizing(
            $notStartedModels->pluck('id')->toArray(),
            $returnedSubscriptions->pluck('id')->toArray(),
        );
    }

    public function test_only_trialling_subscriptions_are_returned_by_scope()
    {
        $startedModelsCount = $this->faker()->randomDigitNotNull();
        Subscription::factory()->count($startedModelsCount)->create([
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $triallingModelsCount = $this->faker()->randomDigitNotNull();
        $triallingModels = Subscription::factory()->count($triallingModelsCount)->create([
            'trial_ends_at' => now()->addDay(),
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(7),
        ]);

        $returnedSubscriptions = Subscription::whereOnTrial();

        $this->assertEqualsCanonicalizing(
            $triallingModels->pluck('id')->toArray(),
            $returnedSubscriptions->pluck('id')->toArray(),
        );
    }

    public function test_only_overdue_subscriptions_are_returned_by_scope()
    {
        $startedModelsCount = $this->faker()->randomDigitNotNull();
        Subscription::factory()
            ->count($startedModelsCount)
            ->create([
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addDays(7),
            ]);

        $overdueModelsCount = $this->faker()->randomDigitNotNull();
        $overdueModels = Subscription::factory()
            ->count($overdueModelsCount)
            ->overdue()
            ->create();

        $returnedSubscriptions = Subscription::whereOverdue();

        $this->assertEqualsCanonicalizing(
            $overdueModels->pluck('id')->toArray(),
            $returnedSubscriptions->pluck('id')->toArray(),
        );
    }
}
