<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create(config('larasubs.tables.subscriptions'), function (Blueprint $table) {
      $table->id();
      $this->subscribableMorph($table);
      $table->foreignIdFor(config('larasubs.models.plan'))
        ->cascadeOnDelete()
        ->cascadeOnUpdate();

      $table->string('slug')->unique();
      $table->json('name');
      $table->json('description')->nullable();

      $table->timestamp('grace_period_ends_at')->nullable();
      $table->timestamp('trial_ends_at')->nullable();
      $table->timestamp('starts_at')->nullable();
      $table->timestamp('ends_at')->nullable();
      $table->timestamp('cancels_at')->nullable();
      $table->timestamp('cancelled_at')->nullable();
      $table->string('timezone')->nullable();

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
    Schema::dropIfExists(config('larasubs.tables.subscriptions'));
  }

  /**
   * Get subscribable morph column data type.
   */
  protected function subscribableMorph(Blueprint &$table)
  {
    if (config('larasubs.tables.subscriptions.uses_uuid')) {
      $table->uuidMorphs('subscribable');
    } else {
      $table->numericMorphs('subscribable');
    }
  }
};
