<?php

namespace Jojostx\Larasubs\Models\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Jojostx\Larasubs\Models\Feature;
use Throwable;

class FeatureNotFoundException extends ModelNotFoundException
{
    /**
     * The intended feature.
     *
     * @var Feature
     */
    protected $feature;

    public function __construct(Feature $feature, int $code = 0, Throwable | null $previous = null)
    {
        $this->feature = $feature;

        parent::__construct('None of the plans grants access to this feature.', $code, $previous);
    }

    /**
     * Get the feature.
     *
     * @return Feature
     */
    public function getFeature()
    {
        return $this->feature;
    }
}
