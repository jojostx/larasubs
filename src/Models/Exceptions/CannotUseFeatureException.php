<?php

namespace Jojostx\Larasubs\Models\Exceptions;

use Jojostx\Larasubs\Models\Feature;
use RuntimeException;
use Throwable;

class CannotUseFeatureException extends RuntimeException
{
  /**
   * The intended feature.
   *
   * @var Feature
   */
  protected $feature;

  /**
   * The intended units.
   *
   * @var int
   */
  protected $units;

  public function __construct(
    $message,
    Feature $feature,
    int $units,
    int $code = 0,
    Throwable|null $previous = null
  ) {
    $this->feature = $feature;
    $this->units = $units;

    parent::__construct($message, $code, $previous);
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

  /**
   * Get the intended units.
   *
   * @return int
   */
  public function getUnits()
  {
    return $this->units;
  }
}
