<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\FinancialDocumentResource;
use App\Models\FinancialDocument;
use App\Models\Order;
use App\Services\FinancialDocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialDocumentController extends Controller
{
    public function issue(Request $request, Order $order, FinancialDocumentService $documents): AnonymousResourceCollection
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        $documents->issuePurchase($order);
        foreach ($order->refunds()->where('status', 'completed')->get() as $refund) {
            $documents->issueRefund($refund);
        }

        return FinancialDocumentResource::collection($order->financialDocuments()->get());
    }

    public function download(Request $request, FinancialDocument $document): StreamedResponse
    {
        abort_unless($document->order()->where('user_id', $request->user()->id)->exists(), 404);
        $disk = Storage::disk($document->pdf_disk);
        abort_unless($disk->exists($document->pdf_path), 404);

        return $disk->download($document->pdf_path, $document->document_number.'.pdf', ['Content-Type' => 'application/pdf']);
    }
}
