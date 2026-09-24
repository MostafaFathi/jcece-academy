<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Payment $payment): StreamedResponse
    {
        abort_unless($payment->order()->whereBelongsTo($request->user())->exists(), 404);
        abort_if($payment->payment_proof === null, 404);

        $disk = Storage::disk((string) config('jcec.commerce.payment_proof_disk', 'local'));
        abort_unless($disk->exists($payment->payment_proof), 404);
        $extension = pathinfo($payment->payment_proof, PATHINFO_EXTENSION);

        return $disk->download($payment->payment_proof, "payment-proof-{$payment->id}.{$extension}");
    }
}
