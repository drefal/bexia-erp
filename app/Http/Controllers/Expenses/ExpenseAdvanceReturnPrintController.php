<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Models\ExpenseAdvance;
use App\Models\TreasuryMovement;
use App\Models\User;
use Illuminate\Http\Response;

class ExpenseAdvanceReturnPrintController extends Controller
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
            (string) $expenseAdvance->status
                === ExpenseAdvance::STATUS_CLOSED,
            404
        );

        abort_unless(
            filled($expenseAdvance->returned_at)
            && filled(
                $expenseAdvance
                    ->return_treasury_movement_id
            ),
            404
        );

        $movement = TreasuryMovement::query()
            ->whereKey(
                $expenseAdvance
                    ->return_treasury_movement_id
            )
            ->where(
                'company_id',
                $expenseAdvance->company_id
            )
            ->where(
                'source_type',
                'expense_advance_return'
            )
            ->where(
                'source_id',
                $expenseAdvance->id
            )
            ->where(
                'status',
                'posted'
            )
            ->firstOrFail();

        $expenseAdvance->loadMissing([
            'company',
            'employee',
            'pettyCashFund.employee',
            'authorizedByEmployee',
        ]);

        $returnedBy = null;

        if ($expenseAdvance->returned_by_user_id) {
            $returnedBy = User::query()
                ->find(
                    $expenseAdvance
                        ->returned_by_user_id
                );
        }


        // GASTOS10N5: consulta del folio ya emitido.
        // La impresion nunca asigna numeros nuevos.
        $voucherFolio = \Illuminate\Support\Facades\DB::table(
            'petty_cash_vouchers'
        )
            ->where('company_id', $expenseAdvance->company_id)
            ->where('petty_cash_fund_id', $expenseAdvance->petty_cash_fund_id)
            ->where('source_type', 'expense_advance_return')
            ->where('source_id', $expenseAdvance->id)
            ->value('folio');

        return response()->view(
            'filament.resources.expense-advance-resource.pages.print-expense-advance-return',
            [
                'record' =>
                    $expenseAdvance,
                'voucherFolio' => $voucherFolio,

                'movement' =>
                    $movement,

                'returnedBy' =>
                    $returnedBy,
            ]
        );
    }
}
