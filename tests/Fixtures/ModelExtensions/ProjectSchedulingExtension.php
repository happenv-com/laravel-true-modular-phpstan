<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions;

use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Models\Project;
use Happenv\LaravelTrueModular\Phpstan\Tests\Fixtures\ModelExtensions\Models\Milestone;
use Happenv\LaravelTrueModular\ModelExtension\ModelExtension;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Adds relations only, so it must contribute no attribute: the relation belongs to
 * the Dynamic Relation Resolver.
 *
 * @property Project $model
 */
final class ProjectSchedulingExtension extends ModelExtension
{
    /** @return HasMany<Milestone, Project> */
    public function milestones(): HasMany
    {
        return $this->model->hasMany(Milestone::class);
    }
}
