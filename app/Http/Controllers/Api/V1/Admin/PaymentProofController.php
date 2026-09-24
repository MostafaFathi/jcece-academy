<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Payment $payment): StreamedResponse
    {
        Gate::authorize('viewProof', $payment);
        abort_if($payment->payment_proof === null, 404);

        $disk = Storage::disk((string) config('jcec.commerce.payment_proof_disk', 'local'));
        abort_unless($disk->exists($payment->payment_proof), 404);
        $extension = pathinfo($payment->payment_proof, PATHINFO_EXTENSION);

        return $disk->download($payment->payment_proof, "payment-proof-{$payment->id}.{$extension}");
    }
}
