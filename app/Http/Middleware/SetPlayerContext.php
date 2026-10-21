<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ContextResolver;
use App\Support\CurrentContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetPlayerContext
{
    public function __construct(
        private readonly ContextResolver $resolver,
        private readonly CurrentContext $context,
    ) {}

    /**
     * Resolve the player's current (profile, trainer) context from the session, validating it
     * and falling back to the first available one.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $current = $this->resolver->resolve($user, $request->session());

            $this->context->set($current['player_id'] ?? null, $current['trainer_id'] ?? null);
        }

        return $next($request);
    }
}
