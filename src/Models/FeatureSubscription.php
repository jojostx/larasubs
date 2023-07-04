<?php

namespace Jojostx\Larasubs\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property bool   $active
 * @property int $used
 * @property \Carbon\Carbon|null $ends_at
 */
class FeatureSubscription extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * {@inheritdoc}
     */
    protected $fillable = [
        'subscription_id',
        'feature_id',
        'active',
        'used',
        'ends_at',
        'timezone',
    ];

    /**
     * {@inheritdoc}
     */
    protected $casts = [
        'subscription_id' => 'integer',
        'feature_id' => 'integer',
        'active' => 'boolean',
        'used' => 'integer',
        'ends_at' => 'datetime',
    ];

    /**
     * Get the table associated with the model.
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
        return $this->belongsTo(config('larasubs.models.feature'));
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
     * Find ended subscriptions.
     */
    public function scopeWhereEnded(Builder $query): Builder
    {
        $date = Carbon::now();

        return $query
            ->where('ends_at', '<', $date);
    }

    public function scopeWhereNotEnded(Builder $query): Builder
    {
        $date = Carbon::now();

        return $query
            ->where('ends_at', '>', $date);
    }

    /**
     * Scope query to return only active models.
     */
    public function scopeWhereActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Scope query to return only inactive models.
     */
    public function scopeWhereNotActive(Builder $query): Builder
    {
        return $query->where('active', false);
    }

    public function activate(): bool
    {
        return $this->update(['active' => true]);
    }

    public function deactivate(): bool
    {
        return $this->update(['active' => false]);
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isInactive(): bool
    {
        return ! $this->isActive();
    }

    /**
     * Check whether usage has ended.
     */
    public function ended(): bool
    {
        if (is_null($this->ends_at)) {
            return false;
        }

        return Carbon::now()->gte($this->ends_at);
    }

    /**
     * Check whether usage has not ended.
     */
    public function notEnded(): bool
    {
        return ! $this->ended();
    }
}
