<?php

namespace Jojostx\Larasubs;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class LarasubsServiceProvider extends ServiceProvider
{
  /**
   * Perform post-registration booting of services.
   *
   * @return void
   */
  public function boot()
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
   * Register any package services.
   *
   * @return void
   */
  public function register()
  {
  }
}
