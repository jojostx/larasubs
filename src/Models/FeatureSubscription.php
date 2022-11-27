<?php

namespace Jojostx\Larasubs\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use Jojostx\Larasubs\Models\Concerns\Ends;

class FeatureSubscription extends Model
{
    use SoftDeletes;
    use Ends;

    /**
     * {@inheritdoc}
     */
    protected $fillable = [
        'subscription_id',
        'feature_id',
        'used',
        'ends_at',
        'timezone'
    ];

    /**
     * {@inheritdoc}
     */
    protected $casts = [
        'subscription_id' => 'integer',
        'feature_id' => 'integer',
        'used' => 'integer',
        'ends_at' => 'datetime',
    ];

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable(): string
    {
        $pivot_table = config('larasubs.tables.feature_subscription');

        return $pivot_table ?? parent::getTable();
    }

    /**
     * Subscription usage always belongs to a plan feature.
     */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(config('larasubs.models.features'));
    }

    /**
     * Subscription usage always belongs to a plan subscription.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(config('larasubs.models.subscription'));
    }

    /**
     * Scope subscription usage by feature name.
     */
    public function scopeWhereFeatureName(Builder $builder, string $featureName): Builder
    {
        $feature = Feature::where('name', $featureName)->first();

        return $builder->where('feature_id', $feature->getKey() ?? null);
    }

    /**
     * Scope subscription usage Where feature slug.
     */
    public function scopeWhereFeatureSlug(Builder $builder, string $featureSlug): Builder
    {
        $feature = Feature::where('slug', $featureSlug)->first();

        return $builder->where('feature_id', $feature->getKey() ?? null);
    }

    /**
     * Check whether usage has been ended or not.
     *
     * @return bool
     */
    public function ended(): bool
    {
        if (is_null($this->ends_at)) {
            return false;
        }

        return Carbon::now()->gte($this->ends_at);
    }
}
