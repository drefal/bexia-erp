<?php

namespace App\Http\Controllers\Expenses;

use App\Filament\Resources\ExpenseReportResource;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ExpenseReport;
use Filament\Facades\Filament;
use Illuminate\Http\Response;

class ExpenseReportTicketController extends Controller
{
    public function __invoke(
        int $tenant,
        ExpenseReport $expenseReport
    ): Response {
        abort_unless(
            (int) $expenseReport->company_id === $tenant,
            404
        );

        $company = Company::findOrFail($tenant);
        $user = auth()->user();

        abort_unless($user, 403);

        // Validar acceso real al tenant antes de activarlo.
        abort_unless(
            method_exists($user, 'canAccessTenant')
                && $user->canAccessTenant($company),
            403
        );

        Filament::setTenant($company);

        // Respetar permisos y alcance por empleado.
        abort_unless(
            ExpenseReportResource::canView($expenseReport),
            403
        );

        // GASTOS10H: imprimir solo comprobaciones aprobadas.
        abort_unless(
            in_array(
                (string) $expenseReport->status,
                ['approved', 'paid', 'closed'],
                true
            ),
            404
        );

        $expenseReport->loadMissing([
            'company',
            'employee',
            'pettyCashFund',
            'lines',
            'lines.spentByEmployee',
        ]);


        // GASTOS10N5: consulta del folio ya emitido.
        // La impresion nunca asigna numeros nuevos.
        $voucherFolio = \Illuminate\Support\Facades\DB::table(
            'petty_cash_vouchers'
        )
            ->where('company_id', $expenseReport->company_id)
            ->where('petty_cash_fund_id', $expenseReport->petty_cash_fund_id)
            ->where('source_type', 'expense_report')
            ->where('source_id', $expenseReport->id)
            ->value('folio');

        return response()->view(
            'reports.expenses.expense-report-ticket',
            ['record' => $expenseReport,
                'voucherFolio' => $voucherFolio]
        );
    }
}
