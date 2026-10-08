<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Models\ExpenseAdvance;
use App\Models\TreasuryCashTransferRequest;
use App\Models\TreasuryMovement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ExpenseAdvanceReimbursementPrintController extends Controller
{
    public function __invoke(
        Request $request,
        $tenant,
        ExpenseAdvance $expenseAdvance
    ): View {
        abort_unless(
            (int) $expenseAdvance->company_id
                === (int) $tenant,
            404
        );

        abort_unless(
            $expenseAdvance
                ->reimbursement_treasury_movement_id,
            404
        );

        $expenseAdvance->loadMissing([
            'company',
            'employee',
            'pettyCashFund.employee',
            'authorizedByEmployee',
        ]);

        $treasuryMovement =
            TreasuryMovement::query()
                ->findOrFail(
                    $expenseAdvance
                        ->reimbursement_treasury_movement_id
                );

        $transferRequest =
            $expenseAdvance
                ->reimbursement_transfer_request_id
                ? TreasuryCashTransferRequest::query()
                    ->find(
                        $expenseAdvance
                            ->reimbursement_transfer_request_id
                    )
                : null;

        return view(
            'filament.resources.expense-advance-resource.pages.'
            . 'print-expense-advance-reimbursement',
            [
                'advance' =>
                    $expenseAdvance,

                'treasuryMovement' =>
                    $treasuryMovement,

                'transferRequest' =>
                    $transferRequest,
            ]
        );
    }
}
