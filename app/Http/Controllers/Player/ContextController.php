<?php

declare(strict_types=1);

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Http\Requests\Player\UpdateContextRequest;
use App\Support\ContextResolver;
use Illuminate\Http\RedirectResponse;

class ContextController extends Controller
{
    public function update(UpdateContextRequest $request, ContextResolver $resolver): RedirectResponse
    {
        $resolver->store(
            $request->session(),
            $request->integer('player_id'),
            $request->integer('trainer_id'),
        );

        return back();
    }
}
