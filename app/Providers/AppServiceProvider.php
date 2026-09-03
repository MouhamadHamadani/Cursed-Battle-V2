<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Character/CharacterItem now declare an explicit $fillable. Without
        // this, Eloquent silently drops a non-fillable key passed to
        // create()/fill()/update() instead of throwing — a "fix" that quietly
        // no-ops is worse than no fix. Scoped to non-production only (matches
        // Laravel's own Model::shouldBeStrict() skeleton convention) so a
        // stray key never 500s a live request; local/testing catch it loud.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Throttle is appended to the 'web' middleware GROUP, not attached to
        // routes/web.php, because Livewire 3 registers POST /livewire/update
        // itself with only the 'web' group (HandleRequests::boot()) before
        // route files even load — middleware declared on a route group in
        // routes/web.php never reaches that endpoint. Every game action goes
        // through that one URL, so the group is the only place a throttle
        // actually covers it. Do not "tidy" this onto routes/web.php.
        RateLimiter::for('game', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));
    }
}
