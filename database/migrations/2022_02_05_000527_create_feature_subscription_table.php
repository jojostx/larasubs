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
        Schema::create(config('larasubs.tables.feature_subscription'), function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(config('larasubs.models.feature'))
                ->cascadeOnDelete();
            $table->foreignIdFor(config('larasubs.models.subscription'))
                ->cascadeOnDelete();

            $table->unsignedDecimal('used')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('timezone')->nullable();

            $table->timestamps();
            $table->softDeletes();

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
        $pivot_table = config('larasubs.tables.feature_subscription');
        $pivot_table = $pivot_table ?? getPivotTableName(config('larasubs.tables.features'), config('larasubs.tables.subscriptions'));

        Schema::dropIfExists($pivot_table);
    }

    public function generateUniqueCompositeIndex(Blueprint &$table)
    {
        if (
            is_string($feature = config('larasubs.models.feature')) &&
            is_string($subscription = config('larasubs.models.subscription'))
        ) {
            $feature = new $feature;
            $subscription = new $subscription;

            $table->unique([$feature->getForeignKey(), $subscription->getForeignKey()]);
        }
    }
};
