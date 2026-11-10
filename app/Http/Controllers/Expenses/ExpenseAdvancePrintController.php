<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Models\ExpenseAdvance;
use Illuminate\Http\Response;

class ExpenseAdvancePrintController extends Controller
{
    public function __invoke(
        int $tenant,
        ExpenseAdvance $expenseAdvance
    ): Response {
        abort_unless(
            (int) $expenseAdvance->company_id === $tenant,
            404
        );

        abort_unless(
            in_array(
                (string) $expenseAdvance->status,
                [
                    ExpenseAdvance::STATUS_PENDING_RECONCILIATION,
                    ExpenseAdvance::STATUS_PENDING_RETURN,
                    ExpenseAdvance::STATUS_PENDING_REIMBURSEMENT,
                    ExpenseAdvance::STATUS_CLOSED,
                ],
                true
            ),
            404
        );

        $expenseAdvance->loadMissing([
            'company',
            'employee',
            'pettyCashFund.employee',
            'approvedBy',
        ]);


        // GASTOS10N5: consulta del folio ya emitido.
        // La impresion nunca asigna numeros nuevos.
        $voucherFolio = \Illuminate\Support\Facades\DB::table(
            'petty_cash_vouchers'
        )
            ->where('company_id', $expenseAdvance->company_id)
            ->where('petty_cash_fund_id', $expenseAdvance->petty_cash_fund_id)
            ->where('source_type', 'expense_advance')
            ->where('source_id', $expenseAdvance->id)
            ->value('folio');

        return response()->view(
            'filament.resources.expense-advance-resource.pages.print-expense-advance',
            [
                'record' => $expenseAdvance,
                'voucherFolio' => $voucherFolio,
            ]
        );
    }
}
