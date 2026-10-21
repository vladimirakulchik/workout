<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Support\CurrentContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits queries to the player context chosen in the header switcher: rows whose
 * `player_profile_id` and `trainer_id` match `CurrentContext`.
 *
 * Usage: put `#[ScopedBy(BelongsToPlayerContext::class)]` on every model that holds per-player
 * training data and has both columns. The scope is inert until the `SetPlayerContext` middleware
 * has run (guests, console, queue, trainer/coach/admin routes); once it ran without any available
 * context, nothing is returned. Use `withoutGlobalScope(BelongsToPlayerContext::class)` for the
 * rare cross-context query (e.g. a family overview).
 *
 * @implements Scope<Model>
 */
class BelongsToPlayerContext implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(CurrentContext::class);

        if (! $context->isActive()) {
            return;
        }

        if ($context->playerId() === null || $context->trainerId() === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder
            ->where($model->qualifyColumn('player_profile_id'), $context->playerId())
            ->where($model->qualifyColumn('trainer_id'), $context->trainerId());
    }
}
