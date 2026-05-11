<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Support\PurchaseDocumentLinks;

class OpenPurchaseOrderFromRequestController extends Controller
{
    public function __invoke(PurchaseRequest $purchaseRequest)
    {
        abort_unless(auth()->check(), 403);

        $order = PurchaseDocumentLinks::orderForRequest((int) $purchaseRequest->getKey());

        if (! $order) {
            return redirect()
                ->back()
                ->with('warning', 'Esta solicitud aún no tiene una orden de compra relacionada.');
        }

        return redirect(
            PurchaseDocumentLinks::orderUrlFromRequest((int) $purchaseRequest->getKey(), (object) $purchaseRequest->toArray())
        );
    }
}
