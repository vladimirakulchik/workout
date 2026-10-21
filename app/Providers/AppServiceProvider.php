<?php

namespace App\Providers;

use App\Support\ContextResolver;
use App\Support\CurrentContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Lab404\Impersonate\Services\ImpersonateManager;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(ContextResolver::class);
        $this->app->scoped(CurrentContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->tagImpersonatedActivity();
    }

    /**
     * Stamp every activity log entry written during an impersonation with the real admin's id.
     */
    protected function tagImpersonatedActivity(): void
    {
        Activity::creating(function (Activity $activity): void {
            $impersonatorId = app(ImpersonateManager::class)->getImpersonatorId();

            if ($impersonatorId !== null) {
                $activity->properties = $activity->properties->put('impersonator_id', $impersonatorId);
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
