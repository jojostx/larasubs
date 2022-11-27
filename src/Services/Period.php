<?php

declare(strict_types=1);

namespace Jojostx\Larasubs\Services;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use InvalidArgumentException;

class Period
{
  const VALID_INTERVAL_TYPES = ['day', 'week', 'month', 'year'];

  /**
   * Starting date of the period.
   */
  protected string|Carbon $starts_at;

  /**
   * Ending date of the period.
   */
  protected string|Carbon $end;

  /**
   * Interval Type [day|week|month|year].
   */
  protected string $interval_type;

  /**
   * Interval count.
   */
  protected int $period = 1;

  /**
   * Create a new Period instance.
   * @throws InvalidArgumentException|InvalidFormatException
   */
  public function __construct(
    string $interval_type = 'month',
    int $count = 1,
    string|Carbon $starts_at = ''
  ) {
    throw_if(
      !in_array($interval_type, self::VALID_INTERVAL_TYPES),
      new InvalidArgumentException("The $interval_type argument must be a valid type (one of the following): [day, week, month, year]")
    );

    $this->interval_type = $interval_type;

    if (empty($starts_at)) {
      $this->starts_at = Carbon::now();
    } elseif (!$starts_at instanceof Carbon) {
      $this->starts_at = Carbon::parse($starts_at);
    } else {
      $this->starts_at = $starts_at;
    }

    $this->period = $count;

    $starts_at = clone $this->starts_at;

    $method = 'add' . ucfirst($this->interval_type) . 's';

    $this->end = $starts_at->{$method}($this->period);
  }

  /**
   * Get start date.
   */
  public function getStartDate(): Carbon
  {
    return $this->starts_at;
  }

  /**
   * Get end date.
   */
  public function getEndDate(): Carbon
  {
    return $this->end;
  }

  /**
   * Get period interval.
   */
  public function getIntervalType(): string
  {
    return $this->interval_type;
  }

  /**
   * Get period interval count.
   */
  public function getIntervalCount(): int
  {
    return $this->period;
  }
}
