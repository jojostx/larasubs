<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Jojostx\Larasubs\Enums\IntervalType;

return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create(config('larasubs.tables.plans'), function (Blueprint $table) {
      $table->id();
      $table->string('slug')->unique();
      $table->json('name');
      $table->json('description')->nullable();

      $table->boolean('active')->default(true);
      $table->{config('larasubs.plan.price_column_type')}('price')->default('0');
      $table->string('currency', 3);

      $table->unsignedInteger('interval')->default(1);
      $table->string('interval_type')->default(IntervalType::MONTH);

      $table->unsignedInteger('trial_interval')->default(0);
      $table->string('trial_interval_type')->default(IntervalType::DAY);

      $table->unsignedInteger('grace_interval')->default(0);
      $table->string('grace_interval_type')->default(IntervalType::DAY);

      $table->unsignedInteger('sort_order')->default(0);

      $table->softDeletes();
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists(config('larasubs.tables.plans'));
  }
};
