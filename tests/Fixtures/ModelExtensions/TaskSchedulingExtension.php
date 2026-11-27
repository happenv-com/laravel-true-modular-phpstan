<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions;

use Happenv\LaravelTrueModular\ModelExtension\ModelExtension;

final class TaskSchedulingExtension extends ModelExtension
{
    public function getRemainingHoursAttribute(): int
    {
        return 0;
    }

    /** @return int<0, max> */
    public function getElapsedHoursAttribute(): int
    {
        return 0;
    }

    /** Not public, so not an attribute anyone can read. */
    protected function getInternalNoteAttribute(): string
    {
        return '';
    }

    /** Public, but not an accessor. */
    public function isOverdue(): bool
    {
        return false;
    }
}
