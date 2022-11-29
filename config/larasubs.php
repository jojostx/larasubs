<?php

declare(strict_types=1);

return [
  // Manage autoload migrations
  'database' => [
    'cancel_migrations_autoloading' => false,
  ],

  // Subscriptions Database Tables
  'tables' => [
    'plans' => 'plans',
    'features' => 'features',
    'subscriptions' => 'subscriptions',
    'feature_plan' => 'feature_plan',
    'feature_subscription' => 'feature_subscription',
  ],

  // Subscriptions Models
  'models' => [
    'plan' => \Jojostx\Larasubs\Models\Plan::class,
    'feature' => \Jojostx\Larasubs\Models\Feature::class,
    'subscription' => \Jojostx\Larasubs\Models\Subscription::class,
    'feature_plan' => \Jojostx\Larasubs\Models\FeaturePlan::class,
    'feature_subscription' => \Jojostx\Larasubs\Models\FeatureSubscription::class,
  ],

  'plan' => [
    // The value should be a valid schema builder blueprint column definition method
    'price_column_type' => 'unsignedInteger',

    // The cast for the price attribute on the plan model
    'price_column_cast' => 'integer',
  ]
];
