<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Jojostx\Larasubs\Enums\IntervalType;

return new class() extends Migration
{
  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    Schema::create(config('larasubs.tables.feature_plan'), function (Blueprint $table) {
      $table->id();
      $table->foreignIdFor(config('larasubs.models.feature'))
        ->cascadeOnDelete();
      $table->foreignIdFor(config('larasubs.models.plan'))
        ->cascadeOnDelete();

      $table->decimal('units')->nullable();

      $table->timestamps();

      $this->generateUniqueCompositeIndex($table);
    });
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    $pivot_table = config('larasubs.tables.feature_plan');
    $pivot_table = $pivot_table ?? getPivotTableName(config('larasubs.tables.features'), config('larasubs.tables.plans'));

    Schema::dropIfExists($pivot_table);
  }

  public function generateUniqueCompositeIndex(Blueprint &$table)
  {
    if (
      is_string($feature = config('larasubs.models.feature')) &&
      is_string($plan = config('larasubs.models.plan'))
    ) {
      $feature = new $feature;
      $plan = new $plan;

      $table->unique([$feature->getForeignKey(), $plan->getForeignKey()]);
    }
  }
};
