<?php

declare(strict_types=1);

namespace App\Actions\Player;

use App\Enums\ShareLinkType;
use App\Models\PlayerProfile;
use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AddChildToTrainer
{
    public function __construct(
        private AssociateWithTrainer $associate,
        private ParseShareLinkCode $parseCode,
    ) {}

    /**
     * Connect the child to the trainer behind a pasted ShareLink or code.
     *
     * @throws ValidationException
     */
    public function viaLink(PlayerProfile $child, string $input): User
    {
        $code = $this->parseCode->handle($input);
        $link = $code === null ? null : ShareLink::findByCode($code);

        if ($link === null || ! $link->isUsable() || $link->type !== ShareLinkType::Player) {
            throw ValidationException::withMessages(['code' => __('This link or code is invalid, expired or not for players.')]);
        }

        if ($this->associate->handle($link, collect([$child]))->isEmpty()) {
            throw ValidationException::withMessages(['code' => __(':name is already connected to this trainer.', ['name' => $child->first_name])]);
        }

        return $link->trainer;
    }

    /**
     * Connect the child to a trainer the parent already trains with.
     *
     * @throws ValidationException
     */
    public function viaMyTrainer(User $parent, PlayerProfile $child, int $trainerId): User
    {
        if (! in_array($trainerId, $parent->connectedTrainerIds(), true)) {
            throw ValidationException::withMessages(['trainer_id' => __('You are not connected to this trainer.')]);
        }

        $trainer = User::query()->findOrFail($trainerId);

        if (! $this->associate->connect($trainer->id, $child)) {
            throw ValidationException::withMessages(['trainer_id' => __(':name is already connected to this trainer.', ['name' => $child->first_name])]);
        }

        return $trainer;
    }
}
