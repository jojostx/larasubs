<?php

namespace Jojostx\Larasubs;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class LarasubsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->configure();
    }

    /**
     * Setup the configuration for Cashier.
     *
     * @return void
     */
    protected function configure()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/larasubs.php',
            'larasubs'
        );
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        $this->bootMigrations();
        $this->bootValidtions();
        $this->bootPublishing();
    }

    /**
     * Boot the package migrations.
     *
     * @return void
     */
    protected function bootMigrations()
    {
        if (! config('larasubs.database.cancel_migrations_autoloading') && $this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
    }

    /**
     * Boot the package migrations.
     *
     * @return void
     */
    protected function bootValidtions()
    {
        // Add strip_tags validation rule
        Validator::extend('strip_tags', function ($attribute, $value) {
            return is_string($value) && strip_tags($value) === $value;
        }, trans('validation.invalid_strip_tags'));

        // Add time offset validation rule
        Validator::extend('timeoffset', function ($attribute, $value) {
            return array_key_exists($value, timeoffsets());
        }, trans('validation.invalid_timeoffset'));
    }

    /**
     * Boot the package's publishable resources.
     *
     * @return void
     */
    protected function bootPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/larasubs.php' => config_path('larasubs.php'),
            ], 'larasubs-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'larasubs-migrations');
        }
    }
}
