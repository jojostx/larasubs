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
    Schema::create(config('larasubs.tables.features'), function (Blueprint $table) {
      $table->id();
      $table->string('slug')->unique();
      $table->json('name');
      $table->json('description')->nullable();

      $table->boolean('consumable')->default(false);

      $table->unsignedInteger('sort_order')->default(0);

      $table->unsignedInteger('interval')->default(1);
      $table->string('interval_type')->default(IntervalType::MONTH);

      $table->softDeletes();
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    Schema::dropIfExists(config('larasubs.tables.features'));
  }
};
