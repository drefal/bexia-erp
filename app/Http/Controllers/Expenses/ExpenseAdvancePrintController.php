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

        return response()->view(
            'filament.resources.expense-advance-resource.pages.print-expense-advance',
            [
                'record' => $expenseAdvance,
            ]
        );
    }
}
