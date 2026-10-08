<?php

namespace App\Jobs;

use App\Mail\TransactionalEventMail;
use App\Models\TransactionalDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendTransactionalDelivery implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(public int $deliveryId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $delivery = DB::transaction(function (): ?TransactionalDelivery {
            $locked = TransactionalDelivery::query()->lockForUpdate()->find($this->deliveryId);
            if ($locked === null || $locked->status === 'sent') {
                return null;
            }
            if ($locked->status === 'sending' && $locked->last_attempt_at?->greaterThan(now()->subMinutes(10))) {
                return null;
            }
            $locked->update([
                'status' => 'sending',
                'attempts' => $locked->attempts + 1,
                'last_attempt_at' => now(),
                'error_code' => null,
                'failed_at' => null,
            ]);

            return $locked->refresh();
        });

        if ($delivery === null) {
            return;
        }

        try {
            Mail::to($delivery->recipient_email)->send(new TransactionalEventMail($delivery));
            $delivery->update(['status' => 'sent', 'sent_at' => now(), 'error_code' => null]);
        } catch (Throwable $exception) {
            $delivery->update(['status' => 'failed', 'failed_at' => now(), 'error_code' => class_basename($exception)]);
            throw $exception;
        }
    }
}
