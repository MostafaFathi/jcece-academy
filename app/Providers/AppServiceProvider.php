<?php

namespace App\Providers;

use App\Contracts\CertificatePdfGenerator;
use App\Contracts\ProtectedVideoProvider;
use App\Models\Course;
use App\Models\Package;
use App\Models\User;
use App\Services\BunnyProtectedVideoProvider;
use App\Services\MpdfCertificatePdfGenerator;
use App\Services\UnconfiguredProtectedVideoProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        $this->app->bind(CertificatePdfGenerator::class, MpdfCertificatePdfGenerator::class);
        $this->app->bind(ProtectedVideoProvider::class, fn ($app): ProtectedVideoProvider => config('jcec.bunny_stream.enabled')
            ? $app->make(BunnyProtectedVideoProvider::class)
            : $app->make(UnconfiguredProtectedVideoProvider::class));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (['send' => 30, 'reaction' => 60, 'group' => 10, 'private' => 10] as $action => $limit) {
            RateLimiter::for('messaging-'.$action, fn (Request $request) => Limit::perMinute($limit)->by((string) $request->user()?->id));
        }
        ResetPassword::createUrlUsing(fn (User $user, string $token): string => url('/reset-password/'.rawurlencode($token)).'?email='.rawurlencode($user->getEmailForPasswordReset()));
        Model::preventLazyLoading(! app()->isProduction());

        Relation::morphMap([
            'course' => Course::class,
            'package' => Package::class,
        ]);
    }
}
