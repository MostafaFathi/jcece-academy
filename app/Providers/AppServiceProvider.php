<?php

namespace App\Providers;

use App\Contracts\CertificatePdfGenerator;
use App\Models\Course;
use App\Models\Package;
use App\Services\MpdfCertificatePdfGenerator;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        Relation::morphMap([
            'course' => Course::class,
            'package' => Package::class,
        ]);
    }
}
