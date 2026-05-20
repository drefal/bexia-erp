<?php

namespace App\Http\Controllers;

use App\Models\AccountPayable;
use App\Models\AccountPayablePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class AccountPayablePrintController extends Controller
{
    public function payable(Request $request, int $tenant, int $payable)
    {
        $this->setPermissionTenant($tenant);
        $this->authorizeView();

        $record = AccountPayable::query()
            ->with(['purchaseOrder', 'purchaseReceipt'])
            ->where('company_id', $tenant)
            ->findOrFail($payable);

        $payments = DB::table('account_payable_payments as p')
            ->leftJoin('treasury_accounts as ta', 'ta.id', '=', 'p.treasury_account_id')
            ->leftJoin('payment_forms as pf', 'pf.id', '=', 'p.payment_form_id')
            ->leftJoin('treasury_movements as tm', 'tm.id', '=', 'p.treasury_movement_id')
            ->where('p.company_id', $tenant)
            ->where('p.account_payable_id', $record->id)
            ->orderBy('p.payment_date')
            ->orderBy('p.id')
            ->get([
                'p.id',
                'p.payment_date',
                'p.amount',
                'p.currency',
                'p.reference',
                'p.status',
                'p.posted_at',
                'p.notes',
                'ta.name as treasury_account_name',
                'pf.code as payment_form_code',
                'pf.name as payment_form_name',
                'tm.id as treasury_movement_id',
                'tm.type as treasury_movement_type',
                'tm.status as treasury_movement_status',
            ]);

        $company = DB::table('companies')->where('id', $tenant)->first();

        $data = [
            'company' => $company,
            'logoSrc' => $this->companyLogoSrc($company),
            'record' => $record,
            'payments' => $payments,
            'printedAt' => now(),
            'title' => 'Cuenta por pagar ' . $record->number,
        ];

        return $this->renderPrint('prints.account-payable', $data, 'cxp-' . $record->number . '.pdf');
    }

    public function payment(Request $request, int $tenant, int $payment)
    {
        $this->setPermissionTenant($tenant);
        $this->authorizeView();

        $record = AccountPayablePayment::query()
            ->with(['accountPayable', 'treasuryAccount', 'paymentForm', 'treasuryMovement'])
            ->where('company_id', $tenant)
            ->findOrFail($payment);

        $company = DB::table('companies')->where('id', $tenant)->first();

        $data = [
            'company' => $company,
            'logoSrc' => $this->companyLogoSrc($company),
            'record' => $record,
            'printedAt' => now(),
            'title' => 'Pago a proveedor #' . $record->id,
        ];

        return $this->renderPrint('prints.account-payable-payment', $data, 'pago-cxp-' . $record->id . '.pdf');
    }

    protected function renderPrint(string $view, array $data, string $filename)
    {
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView($view, $data)
                ->setPaper('letter')
                ->stream($filename);
        }

        return response()->view($view, $data);
    }

    protected function authorizeView(): void
    {
        $user = auth()->user();

        abort_unless($user && method_exists($user, 'can') && $user->can('account_payables.view'), 403);
    }

    protected function setPermissionTenant(int $tenant): void
    {
        if (class_exists(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant);
        }
    }

    protected function companyLogoSrc(?object $company): ?string
    {
        if (! $company) {
            return null;
        }

        $fields = [
            'logo_url',
            'logo_path',
            'logo',
            'image_path',
            'image',
            'avatar',
        ];

        foreach ($fields as $field) {
            $value = isset($company->{$field}) ? trim((string) $company->{$field}) : '';

            if ($value === '') {
                continue;
            }

            if (str_starts_with($value, 'data:image')) {
                return $value;
            }

            if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                return $value;
            }

            $candidates = [
                $value,
                'storage/' . ltrim($value, '/'),
                'images/' . ltrim($value, '/'),
                'logos/' . ltrim($value, '/'),
            ];

            foreach ($candidates as $candidate) {
                $path = public_path(ltrim($candidate, '/'));

                if (is_file($path) && is_readable($path)) {
                    $mime = function_exists('mime_content_type') ? mime_content_type($path) : 'image/png';

                    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
                }
            }
        }

        return null;
    }
}
