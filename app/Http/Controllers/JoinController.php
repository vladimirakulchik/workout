<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ShareLink;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class JoinController extends Controller
{
    private const REASONS = [
        ShareLink::STATUS_REVOKED => 'This link has been deactivated. Please ask your trainer for a new one.',
        ShareLink::STATUS_ACCEPTED => 'This link has already been used. Please ask your trainer for a new one.',
        ShareLink::STATUS_EXPIRED => 'This link has expired. Please ask your trainer for a new one.',
    ];

    public function show(Request $request, string $code): Response
    {
        $link = ShareLink::query()->withoutGlobalScopes()->with('trainer')->where('code', $code)->first();

        if ($link === null || ! $link->isUsable()) {
            $message = $link === null
                ? 'This link is not valid. Please check the address or ask your trainer for a new one.'
                : self::REASONS[$link->status()];

            return Inertia::render('join/Invalid', ['message' => __($message)])
                ->toResponse($request)
                ->setStatusCode($link === null ? 404 : 410);
        }

        $request->session()->put('join_code', $link->code);

        return Inertia::render('join/Show', [
            'trainerName' => $link->trainer->displayName(),
            'type' => $link->type->value,
            'typeLabel' => $link->type->label(),
        ])->toResponse($request);
    }
}
