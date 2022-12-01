<p align="center">
    <img src="./art/larasubs.png" alt="Banner" style="width: 100%;" />
</p>

<p align="center">
<a href="https://packagist.org/packages/jojostx/larasubs">
<img alt="Latest Version on Packagist" src="https://img.shields.io/packagist/v/jojostx/larasubs?include_prereleases">
</a>
<a href="https://github.com/jojostx/larasubs/actions?query=workflow%3Arun-tests+branch%3Amain">
<img alt="GitHub Tests Action Status" src="https://img.shields.io/github/workflow/status/jojostx/larasubs/run-tests">
</a>
<a href="https://github.com/jojostx/larasubs/actions?query=workflow%3Afix-code-style+branch%3Amain">
<img alt="GitHub Code Style Action Status" src="https://github.com/jojostx/larasubs/actions/workflows/fix-code-style.yml/badge.svg">
</a>
<a href="https://packagist.org/packages/jojostx/larasubs">
<img alt="Total Downloads" src="https://img.shields.io/packagist/dt/jojostx/larasubs?color=green&style=flat">
</a>
</p>

## About

This package provides a straightforward interface to handle subscriptions and features consumption.

## Considerations

- Payments are out of scope for this package.
- You may want to extend some of the core models, in case you need to override the logic behind some helper methods like `renew()`, `cancel()` etc. E.g.: when cancelling a subscription you may want to also cancel the recurring payment attached.

## Installation

You can install the package via composer:
```bash
composer require jojostx/larasubs
```

Publish resources (migrations and config files):
```bash
php artisan vendor:publish --provider="Jojostx\Larasubs\LarasubsServiceProvider" --tag="larasubs-config"

php artisan vendor:publish --provider="Jojostx\Larasubs\LarasubsServiceProvider" --tag="larasubs-migrations"
```

## Usage

To start using it, you just have to add the `Jojostx\Larasubs\Models\Concerns\HasSubscriptions` trait to your `User` model (or any model you want to have subscriptions):

```php
<?php
namespace App\Models;

use Jojostx\Larasubs\Models\Concerns\HasSubscriptions;

class User
{
    use HasSubscriptions;
}
```

And that's it!

### Setting Plans Up

First things first, you have to define the plans you'll offer. In the example below, we are creating two plans.

```php
<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Jojostx\Larasubs\Enums\IntervalType;
use Jojostx\Larasubs\Models\Models\Plan;

class PlanSeeder extends Seeder
{
    public function run()
    {
        $silver = Plan::create([
            'name'                    => 'silver',
            'description'             => 'Plan for medium businesses',
            'active'                  => true,
            'price'                   => 100000, // price in the lowest currency value (kobo)
            'currency'                => 'NGN',
            'interval'                => 6,
            'interval_type'           => IntervalType::YEAR,
            'trial_interval'          => 1,
            'trial_interval_type'     => IntervalType::MONTH,
            'grace_interval'          => 1,
            'grace_interval_type'     => IntervalType::MONTH,
            'sort_order' => 1,
        ]);

        $gold = Plan::create([
            'name'                    => 'gold',
            'description'             => 'Plan for large businesses',
            'active'                  => true,
            'price'                   => 10000000, // price in the lowest currency value (kobo)
            'currency'                => 'NGN',
            'interval'                => 6,
            'interval_type'           => IntervalType::YEAR,
            'trial_interval'          => 1,
            'trial_interval_type'     => IntervalType::MONTH,
            'grace_interval'          => 1,
            'grace_interval_type'     => IntervalType::MONTH
        ]);
    }
}
```

Everything here is quite simple, but it is worth to emphasize: by receiving the interval options above, the two plans are defined as yearly with a 1 month trial period and grace period.

#### Trial Period

You can define a trial period for each plan, so your users will can access a plan on trial before the subscription starts:

#### Grace Period

You can define a grace period for each plan, so your users will not loose access to their features immediately when the subscription ends.

### Setting Up Features

Next, you may define the features your plan will offer. In the example below, we are creating two features.
In the example below, we are creating two features: one to handle how much minutes each user can spend with deploys and if they can use subdomains.

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Jojostx\Larasubs\Enums\IntervalType;
use Jojostx\Larasubs\Models\Models\Feature;

class FeatureSeeder extends Seeder
{
    public function run()
    {
        $deployMinutes = Feature::create([
            'name'             => 'deploy-minutes',
            'consumable'       => true,
            'interval'      => 1,
            'interval_type' => IntervalType::DAY,
        ]);

        $customDomain = Feature::create([
            'name'       => 'custom-domain',
            'description' => 'Ability to create and use subdomains',
            'consumable' => false,
            'active' => true,
            'interval'      => 1,
            'interval_type' => IntervalType::DAY,
            'sort_order',
        ]);
    }
}
```

### Associating Plans with Features

As each feature can belong to multiple plans (and they can have multiple features), you have to associate them:

```php
use Jojostx\Larasubs\Models\Feature;

// ...

$deployMinutes = Feature::where('name', 'deploy-minutes')->first();
$subdomains    = Feature::where('name', 'subdomains')->first();

$silver->features()->attach($deployMinutes, ['units' => 15]);

$gold->features()->attach($deployMinutes, ['units' => 25]);
$gold->features()->attach($subdomains);
```

It is necessary to pass a value to `units` when associating a consumable feature with a plan.

In the example above, we are giving 15 minutes of deploy time to silver users and 25 to gold users. We are also allowing gold users to use subdomains.

### Subscribing

Now that you have a set of plans with their own features, it is time to subscribe users to them. Registering subscriptions is quite simple:

```php
<?php

namespace App\Listeners;

use App\Events\PaymentApproved;

class SubscribeUser
{
    public function handle(PaymentApproved $event)
    {
        $subscriber = $event->user;
        $plan       = $event->plan;
        $subscriptionName  = $user->name . strval(time()); // should be unique

        $subscriber->subscribeTo(
            $plan,
            $subscriptionName
        );
    }
}
```

In the example above, we are simulating an application that subscribes its users when their payments are approved. It is easy to see that the method `subscribeTo` requires only two arguments:
 - the plan the user is subscribing to. 
 - the unique name for the subscription.
 
There are other options you can pass to it to handle particular cases that we're gonna cover below.

> By default, the `subscribeTo` method calculates the expiration considering the plan's 'Interval' options, so you don't have to worry about it.

## Testing

```bash
composer test
```

## Security Policy

If you discover any security related issues, please [send an email](mailto:ikuskid7@yahoo.com) instead of using the issue tracker.

## Support

The following support channels are available at your fingertips:

- [Chat on Slack](https://join.slack.com/sharedm/zt-1ktzmsqz8-bj44wjjgtsmaxp8qkskulw)
- [Help on Email](mailto:ikuskid7@yahoo.com)
- [Follow on Twitter](https://twitter.com/Angel_Ikuru)

## License

This software is released under [The MIT License (MIT)](LICENSE.md).

(c) 2022 Jojostx, Some rights reserved.
