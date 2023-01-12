<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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

            $table->integer('units')->nullable();

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
        $pivot_table = config('larasubs.tables.feature_plan');
        $pivot_table = $pivot_table ?? getPivotTableName(config('larasubs.tables.features'), config('larasubs.tables.plans'));

        Schema::dropIfExists($pivot_table);
    }
};
