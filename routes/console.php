<?php

use App\Services\TransactionalDeliveryService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('transactional-deliveries:dispatch-stale', function (): void {
    $count = app(TransactionalDeliveryService::class)->dispatchStaleQueued();
    $this->info("Dispatched {$count} stale queued transactional deliveries.");
})->purpose('Recover transactional deliveries queued before a dispatch interruption');

Schedule::command('notifications:maintain')->hourly()->withoutOverlapping(55)->timezone(config('platform_notifications.timezone'));
Schedule::command('notifications:maintain --digest --prune')->dailyAt('18:00')->withoutOverlapping(55)->timezone(config('platform_notifications.timezone'));
