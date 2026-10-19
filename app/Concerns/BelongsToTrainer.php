<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\DB;

/**
 * Tenant isolation: when a trainer or coach is authenticated, queries are limited to rows
 * whose `trainer_id` is that trainer. Not applied for guests, super admins, players,
 * console or queue contexts (no authenticated user).
 */
trait BelongsToTrainer
{
    public static function bootBelongsToTrainer(): void
    {
        static::addGlobalScope('trainer', new class implements Scope
        {
            public function apply(Builder $builder, Model $model): void
            {
                $user = auth()->user();

                if (! $user instanceof User) {
                    return;
                }

                if ($user->isTrainer()) {
                    $trainerId = $user->id;
                } elseif ($user->isCoach()) {
                    $trainerId = DB::table('coach_profiles')->where('user_id', $user->id)->value('trainer_id');
                } else {
                    return;
                }

                if ($trainerId === null) {
                    $builder->whereRaw('1 = 0');

                    return;
                }

                $builder->where($model->qualifyColumn('trainer_id'), $trainerId);
            }
        });
    }
}
