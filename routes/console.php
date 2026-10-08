<?php

use App\Services\TransactionalDeliveryService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('transactional-deliveries:dispatch-stale', function (): void {
    $count = app(TransactionalDeliveryService::class)->dispatchStaleQueued();
    $this->info("Dispatched {$count} stale queued transactional deliveries.");
})->purpose('Recover transactional deliveries queued before a dispatch interruption');
