<?php

namespace Jojostx\Larasubs\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Jojostx\Larasubs\Models\Concerns\HandlesRecurrence;
use Jojostx\Larasubs\Models\Concerns\HasSchemalessAttributes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\SchemalessAttributes\SchemalessAttributes;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property SchemalessAttributes $description
 * @property bool   $active
 * @property bool   $consumable
 * @property int $interval
 * @property string $interval_type
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property-read \Jojostx\Larasubs\Models\Plan  $plan
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent  $subscriber
 */
class Feature extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasTranslations;
    use HasSlug;
    use SortableTrait;
    use HandlesRecurrence;
    use HasSchemalessAttributes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'consumable',
        'active',
        'interval',
        'interval_type',
        'sort_order',
    ];

    /**
     * {@inheritdoc}
     */
    protected $casts = [
        'slug' => 'string',
        'consumable' => 'boolean',
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public $translatable = [
        'name',
    ];

    public $sortable = [
        'order_column_name' => 'sort_order',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('larasubs.tables.features') ?? parent::getTable();
    }

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    /**
     * The feature may belong to many plans.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function plans(): BelongsToMany
    {
        $pivot_table = config('larasubs.tables.feature_plan');

        return $this->belongsToMany(config('larasubs.models.plan'), $pivot_table)
            ->withPivot('units')
            ->withTimestamps();
    }

    /**
     * Scope query to return only active plans.
     */
    public function scopeWhereActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Scope query to return only inactive plans.
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
}
