<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trainer;

use App\Actions\Trainer\UpdateBranding;
use App\Http\Controllers\Controller;
use App\Http\Requests\Trainer\UpdateBrandingRequest;
use App\Support\BrandingResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BrandingController extends Controller
{
    public function edit(Request $request, BrandingResolver $resolver): Response
    {
        return Inertia::render('trainer/Branding', [
            'current' => $resolver->resolve($request->user(), $request->session()),
        ]);
    }

    public function update(UpdateBrandingRequest $request, UpdateBranding $updateBranding): RedirectResponse
    {
        $updateBranding->handle($request->user(), $request->validated('brand_color'), $request->file('logo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Branding saved.')]);

        return back();
    }

    public function destroy(Request $request, UpdateBranding $updateBranding): RedirectResponse
    {
        $updateBranding->reset($request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Branding reset to default.')]);

        return back();
    }
}
