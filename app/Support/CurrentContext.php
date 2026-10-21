<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Request-scoped holder of the active player context (player profile + trainer).
 *
 * Filled by the `SetPlayerContext` middleware on player routes. Models that use the
 * `BelongsToPlayerContext` concern are limited to this context while it is active.
 */
class CurrentContext
{
    private bool $active = false;

    private ?int $playerId = null;

    private ?int $trainerId = null;

    /**
     * Activate the context. Passing nulls means "active, but the user has nothing to act as".
     */
    public function set(?int $playerId, ?int $trainerId): void
    {
        $this->active = true;
        $this->playerId = $playerId;
        $this->trainerId = $trainerId;
    }

    public function clear(): void
    {
        $this->active = false;
        $this->playerId = null;
        $this->trainerId = null;
    }

    /**
     * Whether a request has resolved a context (scopes only apply then).
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    public function playerId(): ?int
    {
        return $this->playerId;
    }

    public function trainerId(): ?int
    {
        return $this->trainerId;
    }
}
