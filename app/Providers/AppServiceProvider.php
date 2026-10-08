<?php

namespace App\Providers;

use App\Contracts\CertificatePdfGenerator;
use App\Contracts\ProtectedVideoProvider;
use App\Models\Course;
use App\Models\Package;
use App\Models\User;
use App\Services\MpdfCertificatePdfGenerator;
use App\Services\BunnyProtectedVideoProvider;
use App\Services\UnconfiguredProtectedVideoProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        ResetPassword::createUrlUsing(fn (User $user, string $token): string => url('/reset-password/'.rawurlencode($token)).'?email='.rawurlencode($user->getEmailForPasswordReset()));
        Model::preventLazyLoading(! app()->isProduction());

        Relation::morphMap([
            'course' => Course::class,
            'package' => Package::class,
        ]);
    }
}
