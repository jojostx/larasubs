<?php

namespace Jojostx\Larasubs\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Jojostx\Larasubs\Enums\IntervalType;
use Jojostx\Larasubs\Models\Concerns\HandlesRecurrence;
use Jojostx\Larasubs\Services\Period;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

class Plan extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasTranslations;
    use SortableTrait;
    use HasSlug;
    use HandlesRecurrence;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'active',
        'price',
        'currency',
        'interval',
        'interval_type',
        'trial_interval',
        'trial_interval_type',
        'grace_interval',
        'grace_interval_type',
        'sort_order',
    ];

    /**
     * {@inheritdoc}
     */
    protected $casts = [
        'slug' => 'string',
        'active' => 'boolean',
        'price' => 'integer',
        'currency' => 'string',
        'interval' => 'integer',
        'interval_type' => 'string',
        'trial_interval' => 'integer',
        'trial_interval_type' => 'string',
        'grace_interval' => 'integer',
        'grace_interval_type' => 'string',
        'sort_order' => 'integer',
    ];

    public $translatable = ['name', 'description'];

    public $sortable = [
        'order_column_name' => 'sort_order',
    ];

    /**
     * Boot function for using with User Events.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if (!$model->interval) {
                $model->interval_type = IntervalType::MONTH;
            }

            if (!$model->interval) {
                $model->interval = 1;
            }
        });
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable(): string
    {
        return config('larasubs.tables.plans') ?? parent::getTable();
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

    public function features()
    {
        $pivot_table = config('larasubs.tables.feature_plan');

        return $this->belongsToMany(config('larasubs.models.feature'), $pivot_table)
            ->using(config('larasubs.models.feature_plan'))
            ->withPivot('units');
    }

    public function subscriptions()
    {
        return $this->hasMany(config('larasubs.models.subscription'));
    }


    /**
     * Scope query to return only active plans.
     */
    public function scopeWhereActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    /**
     * Scope query to return only inactive plans.
     */
    public function scopeWhereNotActive(Builder $query): Builder
    {
        return $query->where('status', false);
    }

    /**
     * Get plan feature by the given slug.
     */
    public function getFeatureBySlug(string $featureSlug): ?Feature
    {
        return $this->features()->where('slug', $featureSlug)->first();
    }

    public function calculateGracePeriodEnd(?Carbon $graceStart = null)
    {
        $period = new Period($this->grace_interval_type, $this->grace_interval, $graceStart);

        return $period->getEndDate();
    }

    public function calculateTrialPeriodEnd(?Carbon $trialStart = null)
    {
        $period = new Period($this->trial_interval_type, $this->trial_interval, $trialStart);

        return $period->getEndDate();
    }

    public function hasGracePeriod(): bool
    {
        return filled($this->grace_interval) && \filled($this->grace_interval_type);
    }

    public function hasTrialPeriod(): bool
    {
        return filled($this->trial_interval) && \filled($this->trial_interval_type);
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
        return (bool) $this->active;
    }

    public function isInactive(): bool
    {
        return !$this->isActive();
    }

    /**
     * Check if plan is free.
     */
    public function isFree(): bool
    {
        return $this->price <= 0;
    }
}
