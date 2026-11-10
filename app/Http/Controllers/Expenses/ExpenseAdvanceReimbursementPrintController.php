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


        // GASTOS10N5: consulta del folio ya emitido.
        // La impresion nunca asigna numeros nuevos.
        $voucherFolio = \Illuminate\Support\Facades\DB::table(
            'petty_cash_vouchers'
        )
            ->where('company_id', $expenseAdvance->company_id)
            ->where('petty_cash_fund_id', $expenseAdvance->petty_cash_fund_id)
            ->where('source_type', 'expense_advance_reimbursement')
            ->where('source_id', $expenseAdvance->id)
            ->value('folio');

        return view(
            'filament.resources.expense-advance-resource.pages.'
            . 'print-expense-advance-reimbursement',
            [
                'advance' =>
                    $expenseAdvance,
                'voucherFolio' => $voucherFolio,

                'treasuryMovement' =>
                    $treasuryMovement,

                'transferRequest' =>
                    $transferRequest,
            ]
        );
    }
}
