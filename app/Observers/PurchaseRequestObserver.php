<?php

namespace App\Observers;

use App\Models\PurchaseRequest;
use App\Support\PurchaseRequestApprovalEngine;

class PurchaseRequestObserver
{
    public function updated(PurchaseRequest $purchaseRequest): void
    {
        if (! $purchaseRequest->wasChanged('status')) {
            return;
        }

        if ($purchaseRequest->status === 'review') {
            PurchaseRequestApprovalEngine::sendToReview((int) $purchaseRequest->getKey());
            return;
        }

        if (in_array($purchaseRequest->status, ['approved', 'aprobada'], true)) {
            PurchaseRequestApprovalEngine::closeAsApproved(
                (int) $purchaseRequest->getKey(),
                auth()->id(),
                'Solicitud aprobada.'
            );
        }
    }
}
