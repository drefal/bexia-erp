<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Support\PurchaseRequestApprovalActions;
use Illuminate\Http\Request;

class PurchaseRequestApprovalController extends Controller
{
    public function approve(PurchaseRequest $purchaseRequest)
    {
        abort_unless(auth()->check(), 403);

        try {
            PurchaseRequestApprovalActions::approve(
                (int) $purchaseRequest->getKey(),
                (int) auth()->id()
            );

            return redirect()
                ->back()
                ->with('success', 'Solicitud de compra aprobada.');
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, PurchaseRequest $purchaseRequest)
    {
        abort_unless(auth()->check(), 403);

        $reason = trim((string) $request->input('reason', ''));

        try {
            PurchaseRequestApprovalActions::reject(
                (int) $purchaseRequest->getKey(),
                (int) auth()->id(),
                $reason
            );

            return redirect()
                ->back()
                ->with('success', 'Solicitud de compra rechazada.');
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }
}
