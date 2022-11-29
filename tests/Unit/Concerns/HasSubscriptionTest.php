<?php

namespace Jojostx\Larasubs\Tests\Unit\Concerns;

use LucasDotVin\DBQueriesCounter\Traits\CountsQueries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Jojostx\Larasubs\Events\SubscriptionCreated;
use Jojostx\Larasubs\Events\SubscriptionPlanChanged;
use Jojostx\Larasubs\Events\SubscriptionStarted;
use Jojostx\Larasubs\Models\Plan;
use Jojostx\Larasubs\Tests\Fixtures\Models\User;
use Jojostx\Larasubs\Tests\TestCase;

class HasSubscriptionsTest extends TestCase
{
  use CountsQueries;
  use RefreshDatabase;
  use WithFaker;

  public function testModelCanSubscribeToAPlan()
  {
    $plan = Plan::factory()->createOne();
    $subscriber = User::factory()->createOne();

    Event::fake();

    $subscription = $subscriber->subscribeTo($plan, 'Test Subscription');

    Event::assertDispatched(SubscriptionCreated::class);
    Event::assertDispatched(SubscriptionStarted::class);

    $starts_at = now()->toDateTimeString();
    $ends_at = $plan->calculateNextRecurrenceEnd();
    $trial_ends_at = $plan->calculateTrialPeriodEnd()->toDateTimeString();
    $grace_ends_at = $plan->calculateGracePeriodEnd($ends_at)->toDateTimeString();

    $this->assertDatabaseHas('subscriptions', [
      'id' => $subscription->id,
      'plan_id' => $plan->id,
      'subscribable_id' => $subscriber->getKey(),
      'subscribable_type' => $subscriber->getMorphClass(),
      'starts_at' => $starts_at,
      'ends_at' => $ends_at->toDateTimeString(),
      'trial_ends_at' => $trial_ends_at,
      'grace_ends_at' => $grace_ends_at,
    ]);
  }

  public function testModelDefinesGracePeriodEnd()
  {
    $plan = Plan::factory()
      ->withGracePeriod()
      ->createOne();

    $subscriber = User::factory()->createOne();
    $subscription = $subscriber->subscribeTo($plan);

    $this->assertDatabaseHas('subscriptions', [
      'grace_ends_at' => $plan->calculateGracePeriodEnd($subscription->ends_at),
    ]);
  }

  public function testModelCanChangeASubscriptionPlan()
  {
    Carbon::setTestNow(now());

    $oldPlan = Plan::factory()->createOne();
    $newPlan = Plan::factory()->createOne();

    $subscriber = User::factory()->createOne();
    $subscription = $subscriber->subscribeTo($oldPlan);

    Event::fake();

    $f_subscription = $subscriber->subscriptions()->first();
    $f_subscription->changePlan($newPlan);

    Event::assertDispatched(SubscriptionPlanChanged::class);

    $this->assertDatabaseHas('subscriptions', [
      'id' => $f_subscription->id,
      'plan_id' => $newPlan->id,
      'subscribable_id' => $subscriber->id,
      'starts_at' => now(),
      'ends_at' => $newPlan->calculateNextRecurrenceEnd(),
    ]);

    $this->assertEquals($f_subscription->getKey(), $subscription->getKey());
  }

  public function testModelCanGetAllSubscriptions()
  {
    Carbon::setTestNow(now());

    $plan = Plan::factory()->createOne();

    $subscriber = User::factory()->createOne();

    $f_subscription = $subscriber->subscribeTo($plan);
    $s_subscription = $subscriber->subscribeTo($plan);
    $t_subscription = $subscriber->subscribeTo($plan);

    $allSubs = $subscriber->subscriptions;

    $this->assertTrue($allSubs->contains($f_subscription));
    $this->assertTrue($allSubs->contains($s_subscription));
    $this->assertTrue($allSubs->contains($t_subscription));
  }

  public function testModelGetLatestSubscription()
  {
    Carbon::setTestNow(now());

    $plan = Plan::factory()->createOne();

    $subscriber = User::factory()->createOne();

    $f_subscription = $subscriber->subscribeTo($plan);
    $s_subscription = $subscriber->subscribeTo($plan);

    $this->assertTrue($s_subscription->is($subscriber->fresh()->subscription));
    $this->assertTrue($f_subscription->isNot($subscriber->fresh()->subscription));
  }

  public function testModelCanGetSubscriptionBySlug()
  {
    Carbon::setTestNow(now());

    $plan = Plan::factory()->createOne();

    $subscriber = User::factory()->createOne();

    $f_subscription = $subscriber->subscribeTo($plan);

    $this->assertTrue($f_subscription->is($subscriber->getSubscriptionBySlug($f_subscription->slug)));
  }

  public function testModelCanGetOnlyActiveSubscriptions()
  {
    Carbon::setTestNow(now());

    $plan = Plan::factory()->createOne();

    $subscriber = User::factory()->createOne();

    $f_subscription = $subscriber->subscribeTo($plan);
    $s_subscription = $subscriber->subscribeTo($plan);
    $t_subscription = $subscriber->subscribeTo($plan);

    $t_subscription->cancelImmediately();

    $activeSubs = $subscriber->activeSubscriptions();

    $this->assertTrue($activeSubs->contains($f_subscription));
    $this->assertTrue($activeSubs->contains($s_subscription));
    $this->assertFalse($activeSubs->contains($t_subscription));
  }

  public function testModelCanGetOnlyInactiveSubscriptions()
  {
    Carbon::setTestNow(now());

    $plan = Plan::factory()->createOne();

    $subscriber = User::factory()->createOne();

    $f_subscription = $subscriber->subscribeTo($plan);
    $s_subscription = $subscriber->subscribeTo($plan);
    $t_subscription = $subscriber->subscribeTo($plan);

    $t_subscription->cancelImmediately();

    $inactiveSubs = $subscriber->inactiveSubscriptions();

    $this->assertFalse($inactiveSubs->contains($f_subscription));
    $this->assertFalse($inactiveSubs->contains($s_subscription));
    $this->assertTrue($inactiveSubs->contains($t_subscription));
  }

  public function testModelCanCheckIfActiveSubscriptionExistForPlan()
  {
    Carbon::setTestNow(now());

    $plan = Plan::factory()->createOne();

    $subscriber = User::factory()->createOne();

    $f_subscription = $subscriber->subscribeTo($plan);
    $s_subscription = $subscriber->subscribeTo($plan);
    $t_subscription = $subscriber->subscribeTo($plan);

    $this->assertTrue($subscriber->hasActiveSubscriptionTo($plan));

    $f_subscription->cancelImmediately();
    $s_subscription->cancelImmediately();
    $t_subscription->cancelImmediately();
    
    $this->assertFalse($subscriber->hasActiveSubscriptionTo($plan));
  }

  public function testModelCanCheckIfInActiveSubscriptionExistForPlan()
  {
    Carbon::setTestNow(now());

    $plan = Plan::factory()->createOne();

    $subscriber = User::factory()->createOne();

    $f_subscription = $subscriber->subscribeTo($plan);
    $s_subscription = $subscriber->subscribeTo($plan);
    $t_subscription = $subscriber->subscribeTo($plan);

    $this->assertFalse($subscriber->hasInactiveSubscriptionTo($plan));

    $f_subscription->cancelImmediately();
    $s_subscription->cancelImmediately();
    $t_subscription->cancel();
    
    $this->assertTrue($subscriber->hasInactiveSubscriptionTo($plan));
  }
}
