<?php

namespace App\Observers;

use App\Models\PurchaseRequest;
use App\Support\PurchaseRequestApprovalEngine;
use Illuminate\Support\Facades\Log;

class PurchaseRequestObserver
{
    public function updated(PurchaseRequest $purchaseRequest): void
    {
        if (! $purchaseRequest->wasChanged('status')) {
            return;
        }

        if ($purchaseRequest->status === 'review') {
            $approvalRequestId = PurchaseRequestApprovalEngine::sendToReview(
                (int) $purchaseRequest->getKey()
            );

            Log::info('purchase_request.review.sent', [
                'purchase_request_id' => (int) $purchaseRequest->getKey(),
                'company_id' => (int) ($purchaseRequest->company_id ?? 0),
                'actor_user_id' => auth()->id(),
                'approval_request_id' => $approvalRequestId,
                'workflow_created' => $approvalRequestId !== null,
            ]);

            return;
        }

        if (in_array($purchaseRequest->status, ['approved', 'aprobada'], true)) {
            Log::info('purchase_request.approval.direct', [
                'purchase_request_id' => (int) $purchaseRequest->getKey(),
                'company_id' => (int) ($purchaseRequest->company_id ?? 0),
                'actor_user_id' => auth()->id(),
                'from_status' => (string) ($purchaseRequest->getOriginal('status') ?? ''),
                'to_status' => (string) $purchaseRequest->status,
            ]);
        }

        /*
         * V5.83.6L3A2
         *
         * La aprobación final de un workflow debe realizarla
         * PurchaseRequestApprovalActions::approve().
         *
         * Una aprobación directa no debe cerrar approval_requests
         * históricos o todavía pendientes.
         */
    }
}
