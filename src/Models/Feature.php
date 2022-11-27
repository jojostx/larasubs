<?php

namespace Jojostx\Larasubs\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Jojostx\Larasubs\Models\Concerns\HandlesRecurrence;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

class Feature extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasTranslations;
    use SortableTrait;
    use HandlesRecurrence;
    use Concerns\HasSlug;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'consumable',
        'sort_order',
        'interval',
        'interval_type',
    ];

    /**
     * {@inheritdoc}
     */
    protected $casts = [
        'slug' => 'string',
        'consumable' => 'boolean',
        'sort_order' => 'integer',
    ];

    public $translatable = [
        'name',
        'description',
        'slug'
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
            ->doNotGenerateSlugsOnUpdate()
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
            ->withPivot('units');
    }
}
