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

  public function testModelPlanCanBeChanged()
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

  public function testModelRenews()
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

  public function testModelRenewsBasedOnCurrentDateIfOverdue()
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

  public function testModelCanStart()
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
      'starts_at' => today(),
    ]);
  }

  public function testModelCanScheduleStart()
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

  public function testModelCanCancel()
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

    $subscription->cancel();

    Event::assertDispatched(SubscriptionCancelled::class);

    $this->assertDatabaseHas('subscriptions', [
      'id' => $subscription->id,
      'cancels_at' => now(),
    ]);
  }

  public function testModelCanBeCancelledImmediately()
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

  public function testModelConsidersGracePeriodOnOverdue()
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

  public function testModelWhereActiveScope()
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

  public function testModelWhereNotActiveScope()
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
      ->ended()
      ->notCancelled()
      ->create();

    $notActiveSubscriptions = Subscription::whereNotActive()->get();

    $this->assertCount($inactiveSubscriptionCount, $notActiveSubscriptions);

    $inactiveSubscriptions->each(
      fn ($subscription) => $this->assertContains($subscription->id, $notActiveSubscriptions->pluck('id'))
    );
  }

  public function testModelReturnsNotStartedSubscriptionsInNotActiveScope()
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

  public function testModelReturnsEndedSubscriptionsInNotActiveScope()
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
      ->ended()
      ->notCancelled()
      ->create();

    $notActiveSubscriptions = Subscription::whereNotActive()->get();

    $this->assertCount($endedSubscriptionCount, $notActiveSubscriptions);
    $endedSubscriptions->each(
      fn ($subscription) => $this->assertContains($subscription->id, $notActiveSubscriptions->pluck('id'))
    );
  }

  public function testModelReturnsCancelledSubscriptionsInNotActiveScope()
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

  public function testModelReturnsOnlyCancelledSubscriptionsWithTheScope()
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

  public function testModelReturnsOnlyNotCancelledSubscriptionsWithTheScope()
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

  public function testOnlyStartedModelsAreReturnedByScope()
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

  public function testOnlyNotStartedModelsAreReturnedByScope()
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

  public function testOnlyTriallingModelsAreReturnedByScope()
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

  public function testOnlyOverdueModelsAreReturnedByScope()
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
