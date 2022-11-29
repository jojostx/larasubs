<?php

namespace Jojostx\Larasubs\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class FeaturePlan extends Pivot
{
    protected $fillable = [
        'units',
    ];

    protected $cast = [
        'units' => 'integer',
    ];

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('larasubs.tables.feature_plan') ?? parent::getTable();
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(config('larasubs.models.feature'));
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(config('larasubs.models.plan'));
    }
}
