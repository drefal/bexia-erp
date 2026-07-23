<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class PosController extends Controller
{
    public function open(int $posPoint)
    {
        abort_unless(auth()->check(), 403);

        $pos = $this->posPoint($posPoint);
        $this->v5327bAbortIfCannotAccessPos($pos);

        $this->authorizePos($pos);

        $cashiers = $this->staffForPos($pos);

        return view('pos.cashiers', [
            'pos' => $pos,
            'cashiers' => $cashiers,
            'warehouseName' => $this->labelFromTable('warehouses', $pos->warehouse_id ?? null),
            'locationName' => $this->labelFromTable('stock_locations', $this->configuredStockLocationId($pos)),
        ]);
    }

    public function selectCashier(Request $request, int $posPoint, string $cashier)
    {
        abort_unless(auth()->check(), 403);

        $pos = $this->posPoint($posPoint);
        $this->v5327bAbortIfCannotAccessPos($pos);
        $this->authorizePos($pos);

        $cashierRow = $this->resolveStaff($pos, $cashier);

        abort_if(! $cashierRow, 404);

        if ($this->staffHasPin($cashierRow)) {
            return view('pos.pin', [
                'pos' => $pos,
                'cashier' => $cashierRow,
                'staffKey' => $this->staffKey($cashierRow),
            ]);
        }

        $sessionId = $this->openSession($pos, $cashierRow, $this->v5487OpeningCashFromRequest($request, $cashierRow));

                $this->v5487dApplyOpeningCashToSession((int) $sessionId, $request, $cashierRow);
return redirect('/pos/sessions/' . $sessionId . '/screen');
    }

    public function loginCashier(Request $request, int $posPoint, string $cashier)
    {
        abort_unless(auth()->check(), 403);

        $pos = $this->posPoint($posPoint);
        $this->v5327bAbortIfCannotAccessPos($pos);
        $this->authorizePos($pos);

        $cashierRow = $this->resolveStaff($pos, $cashier);

        abort_if(! $cashierRow, 404);

        $pin = trim((string) $request->input('pin'));

        $hash = $this->staffPinHash($cashierRow);
        $plain = $this->staffPlainPin($cashierRow);

        $validPin = ($hash && Hash::check($pin, (string) $hash))
            || ($plain && hash_equals((string) $plain, (string) $pin));

        if (! $validPin) {
            return redirect()
                ->back()
                ->with('error', 'Clave incorrecta.');
        }

        $sessionId = $this->openSession($pos, $cashierRow, $this->v5487OpeningCashFromRequest($request, $cashierRow));

                $this->v5487dApplyOpeningCashToSession((int) $sessionId, $request, $cashierRow);
return redirect('/pos/sessions/' . $sessionId . '/screen');
    }

    public function screen(int $session)
    {
        abort_unless(auth()->check(), 403);

        $sessionRow = DB::table('pos_sessions')
            ->where('id', $session)
            ->first();
abort_if(! $sessionRow, 404);

        $pos = $this->posPoint((int) $sessionRow->pos_point_id);
        $this->authorizePos($pos);

        $cashier = $this->sessionStaff($sessionRow);
        $staffPermissions = $this->staffPermissionsForSession($pos, $sessionRow, $cashier);

        $warehouseName = $this->labelFromTable('warehouses', $pos->warehouse_id ?? null);
        $locationId = $this->configuredStockLocationId($pos);
        $locationName = $this->labelFromTable('stock_locations', $locationId);
        $customer = $this->defaultCustomer($pos);
        $categories = $this->realCategories($pos);
        $products = $this->realProducts($pos);
        $paymentMethods = $this->paymentMethodsForPos($pos);
        $cashDenominations = $this->jsonArray($pos->cash_denominations ?? null, ['0.50', '1.00', '2.00', '5.00', '10.00', '20.00', '50.00', '100.00', '200.00', '500.00']);

        // V5.49.9A - Permiso configurable para cambio manual de lista de precios.
        $v5499aUser = auth()->user();
        $canChangePriceList = true;

        if (
            \Illuminate\Support\Facades\Schema::hasTable('permissions')
            && $v5499aUser
            && method_exists($v5499aUser, 'can')
        ) {
            $canChangePriceList =
                (method_exists($v5499aUser, 'isSystemAdmin') && $v5499aUser->isSystemAdmin())
                || (method_exists($v5499aUser, 'isGroupAdmin') && $v5499aUser->isGroupAdmin())
                || $v5499aUser->can('pos.change_price_list');
        }

        return view('pos.screen', [
            'session' => $sessionRow,
            'pos' => $pos,
            'cashier' => $cashier,
            'staffPermissions' => $staffPermissions,
            'effectiveRoleLabel' => $this->roleLabel($staffPermissions['role'] ?? null),
            'warehouseName' => $warehouseName,
            'locationName' => $locationName,
            'customer' => $customer,
            'categories' => $categories,
            'products' => $products,
            'paymentMethods' => $paymentMethods,
            'canChangePriceList' => $canChangePriceList,
            'currencies' => $this->currenciesForPos($pos),
            'cashDenominationsReal' => $this->cashDenominationsForPos($pos),
            'cashDenominations' => $cashDenominations,
            'showStock' => (bool) ($pos->show_stock ?? true),
            'allowOutOfStockSales' => (bool) ($pos->allow_out_of_stock_sales ?? false),
            'hideCost' => (bool) ($pos->hide_cost ?? true),
            'hideMargin' => (bool) ($pos->hide_margin ?? true),
            'showProductInfo' => (bool) ($pos->show_product_info ?? true),
            'priceMode' => (string) ($pos->price_mode ?? 'tax_included'),
            'inventoryUpdateMode' => (string) ($pos->inventory_update_mode ?? 'real_time'),
            'receiptHeader' => (string) ($pos->receipt_header ?? ''),
            'receiptFooter' => (string) ($pos->receipt_footer ?? ''),
            'ticketLogoUrl' => $this->ticketLogoUrl($pos),
            'invoiceQrUrl' => (string) ($pos->invoice_qr_url ?? ''),
            'showOrderReferenceOnTicket' => (bool) ($pos->show_order_reference_on_ticket ?? true),
        ]);
    }


    protected function staffForPos(object $pos)
    {
        if (Schema::hasTable('pos_point_employee') && Schema::hasTable('employees')) {
            $rows = DB::table('pos_point_employee as pe')
                ->join('employees as e', 'e.id', '=', 'pe.employee_id')
                ->where('pe.pos_point_id', $pos->id)
                ->where('pe.is_active', true)
                ->where(function ($q) {
                    $q->whereNull('e.pos_active')->orWhere('e.pos_active', true);
                })
                ->orderBy('e.name')
                ->selectRaw("
                    pe.id as assignment_id,
                    pe.employee_id,
                    pe.role,
                    pe.can_create_ticket,
                    pe.can_charge,
                    pe.can_discount,
                    pe.can_cancel,
                    pe.can_open_cash_drawer,
                    pe.max_discount_percent,
                    e.name,
                    e.employee_number as code,
                    e.pin_code,
                    e.pos_pin_hash,
                    null::bigint as legacy_cashier_id
                ")
                ->get();

            if ($rows->isNotEmpty()) {
                return $rows;
            }
        }

        if (Schema::hasTable('pos_cashiers')) {
            return DB::table('pos_cashiers')
                ->where('pos_point_id', $pos->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(function ($row) {
                    $row->assignment_id = null;
                    $row->employee_id = null;
                    $row->role = 'cashier';
                    $row->pin_code = null;
                    $row->pos_pin_hash = $row->pin_hash ?? null;
                    $row->legacy_cashier_id = $row->id;
                    return $row;
                });
        }

        return collect();
    }

    protected function resolveStaff(object $pos, string $staffKey): ?object
    {
        $staff = $this->staffForPos($pos);

        foreach ($staff as $row) {
            $key = ! empty($row->employee_id)
                ? ('emp_' . $row->employee_id)
                : ('cashier_' . ($row->legacy_cashier_id ?? $row->id ?? ''));

            if ($key === $staffKey || (string) ($row->id ?? '') === $staffKey) {
                return $row;
            }
        }

        return null;
    }

    protected function sessionStaff(object $session): ?object
    {
        if (! empty($session->employee_id) && Schema::hasTable('employees')) {
            $employee = DB::table('employees')->where('id', $session->employee_id)->first();

            if ($employee) {
                $employee->role = $session->staff_role ?? null;
                return $employee;
            }
        }

        if (! empty($session->pos_cashier_id) && Schema::hasTable('pos_cashiers')) {
            return DB::table('pos_cashiers')->where('id', $session->pos_cashier_id)->first();
        }

        return null;
    }

    protected function staffKey(object $staff): string
    {
        return ! empty($staff->employee_id)
            ? ('emp_' . $staff->employee_id)
            : ('cashier_' . ($staff->legacy_cashier_id ?? $staff->id ?? ''));
    }

    protected function staffHasPin(object $staff): bool
    {
        return ! empty($staff->pos_pin_hash) || ! empty($staff->pin_code) || ! empty($staff->pin_hash);
    }

    protected function staffPinHash(object $staff): ?string
    {
        return $staff->pos_pin_hash ?? $staff->pin_hash ?? null;
    }

    protected function staffPlainPin(object $staff): ?string
    {
        return $staff->pin_code ?? null;
    }

    protected function currenciesForPos(object $pos): array
    {
        if (! Schema::hasTable('currencies')) {
            return [];
        }

        $ids = $this->jsonArray($pos->currency_ids ?? null, []);

        $query = DB::table('currencies')->where('is_active', true);

        if ($ids) {
            $query->whereIn('id', $ids);
        } elseif (! empty($pos->default_currency_id)) {
            $query->where('id', $pos->default_currency_id);
        }

        return $query
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'code' => $row->code,
                'internal_reference' => (string) ($row->internal_reference ?? $row->default_code ?? $row->reference ?? $row->sku ?? $row->barcode ?? ''),
                'name' => $row->name,
                'symbol' => $row->symbol,
            ])
            ->all();
    }

    private function cashDenominationsForPos(object $pos): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('cash_denominations')) {
            return [];
        }

        $ids = $this->jsonArray($pos->cash_denomination_ids ?? null, []);
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids))));

        $query = \Illuminate\Support\Facades\DB::table('cash_denominations')
            ->where('is_active', true);

        if (! empty($ids)) {
            $query->whereIn('id', $ids);
        } elseif (\Illuminate\Support\Facades\Schema::hasColumn('cash_denominations', 'company_id')) {
            $query->where('company_id', (int) ($pos->company_id ?? 0));
        }

        return $query
            ->orderByRaw("case when type = 'coin' then 0 else 1 end")
            ->orderBy('value')
            ->orderBy('id')
            ->get()
            ->unique(fn ($row): string => (string) ($row->company_id ?? '') . '|' . (string) $row->value . '|' . (string) $row->type)
            ->map(function ($row): array {
                $name = trim((string) ($row->name ?? 'Denominación'));
                $value = (float) ($row->value ?? 0);

                return [
                    'id' => (int) $row->id,
                    'name' => $name,
                    'label' => $name . ' - $' . number_format($value, 2),
                    'value' => $value,
                    'type' => (string) ($row->type ?? 'cash'),
                ];
            })
            ->values()
            ->all();
    }




    protected function staffPermissionsForSession(object $pos, object $session, ?object $cashier): array
    {
        $defaults = [
            'role' => $session->staff_role ?? ($cashier->role ?? 'mixed'),
            'can_create_ticket' => true,
            'can_charge' => true,
            'can_discount' => true,
            'can_cancel' => true,
            'can_open_cash_drawer' => false,
            'max_discount_percent' => 0,
            'source' => 'default',
        ];

        if (
            Schema::hasTable('pos_point_employee')
            && ! empty($session->employee_id)
        ) {
            $assignment = DB::table('pos_point_employee')
                ->where('pos_point_id', $pos->id)
                ->where('employee_id', $session->employee_id)
                ->where('is_active', true)
                ->orderByDesc('id')
                ->first();

            if ($assignment) {
                $role = (string) ($assignment->role ?: 'mixed');

                $permissions = [
                    'role' => $role,
                    'can_create_ticket' => (bool) $assignment->can_create_ticket,
                    'can_charge' => (bool) $assignment->can_charge,
                    'can_discount' => (bool) $assignment->can_discount,
                    'can_cancel' => (bool) $assignment->can_cancel,
                    'can_open_cash_drawer' => (bool) $assignment->can_open_cash_drawer,
                    'max_discount_percent' => (float) $assignment->max_discount_percent,
                    'source' => 'pos_point_employee',
                ];

                return $this->applyBoxTypeRestrictions($pos, $permissions);
            }
        }

        return $this->applyBoxTypeRestrictions($pos, $defaults);
    }

    protected function applyBoxTypeRestrictions(object $pos, array $permissions): array
    {
        $boxType = (string) ($pos->box_type ?? 'mixed');
        $role = (string) ($permissions['role'] ?? 'mixed');

        if ($boxType === 'seller') {
            $permissions['can_charge'] = false;
            $permissions['can_open_cash_drawer'] = false;

            if ($role === 'cashier') {
                $permissions['role'] = 'seller';
            }
        }

        if ($boxType === 'cashier') {
            $permissions['can_create_ticket'] = false;

            if ($role === 'seller') {
                $permissions['role'] = 'cashier';
            }
        }

        if ($role === 'seller') {
            $permissions['can_charge'] = false;
            $permissions['can_open_cash_drawer'] = false;
        }

        if ($role === 'cashier') {
            $permissions['can_create_ticket'] = false;
        }

        if ($role === 'mixed') {
            // Respeta los permisos configurados en Personal de cajas.
        }

        return $permissions;
    }

    protected function roleLabel(?string $role): string
    {
        return match ((string) $role) {
            'seller' => 'Vendedor',
            'cashier' => 'Cajero',
            'mixed' => 'Mixto',
            default => 'Mixto',
        };
    }



    protected function canManageAllPos(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        foreach (['isSystemAdmin', 'isGroupAdmin', 'isCompanyAdmin', 'isAdmin'] as $method) {
            if (method_exists($user, $method) && $user->{$method}()) {
                return true;
            }
        }

        if (method_exists($user, 'can')) {
            foreach (['company.update', 'pos.manage', 'pos.view_all', 'punto_de_venta.manage'] as $permission) {
                try {
                    if ($user->can($permission)) {
                        return true;
                    }
                } catch (\Throwable $e) {
                    //
                }
            }
        }

        return false;
    }

    protected function assignedPosPointIdsForCurrentUser(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        $ids = collect();

        if (
            \Illuminate\Support\Facades\Schema::hasTable('employees')
            && \Illuminate\Support\Facades\Schema::hasTable('pos_point_employee')
        ) {
            $employeeIds = \Illuminate\Support\Facades\DB::table('employees')
                ->where('user_id', $user->id)
                ->pluck('id')
                ->values()
                ->all();

            if ($employeeIds) {
                $assigned = \Illuminate\Support\Facades\DB::table('pos_point_employee')
                    ->whereIn('employee_id', $employeeIds)
                    ->where('is_active', true)
                    ->pluck('pos_point_id');

                $ids = $ids->merge($assigned);
            }
        }

        // Compatibilidad legacy: tabla vieja de cajeros PDV.
        if (\Illuminate\Support\Facades\Schema::hasTable('pos_cashiers')) {
            $legacy = \Illuminate\Support\Facades\DB::table('pos_cashiers')
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->pluck('pos_point_id');

            $ids = $ids->merge($legacy);
        }

        return $ids
            ->filter()
            ->unique()
            ->values()
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function applyPosAccessFilter($posPoints)
    {
        if ($this->canManageAllPos()) {
            return $posPoints;
        }

        $assignedIds = $this->assignedPosPointIdsForCurrentUser();

        if (method_exists($posPoints, 'whereIn')) {
            return $posPoints->whereIn('id', $assignedIds)->values();
        }

        return collect($posPoints)->whereIn('id', $assignedIds)->values();
    }

    protected function abortIfCannotAccessPos(object $pos): void
    {
        if ($this->canManageAllPos()) {
            return;
        }

        $assignedIds = $this->assignedPosPointIdsForCurrentUser();

        abort_unless(in_array((int) $pos->id, $assignedIds, true), 403);
    }

    protected function currentCompanyLabel(?int $companyId = null): string
    {
        $companyId = $companyId ?: $this->currentCompanyId();

        if (! $companyId) {
            return '—';
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('companies')) {
            return 'Empresa #' . $companyId;
        }

        $company = \Illuminate\Support\Facades\DB::table('companies')
            ->where('id', $companyId)
            ->first();

        if (! $company) {
            return 'Empresa #' . $companyId;
        }

        return $company->name
            ?? $company->commercial_name
            ?? $company->business_name
            ?? $company->legal_name
            ?? ('Empresa #' . $companyId);
    }


    protected function posPoint(int $id): object
    {
        abort_unless(Schema::hasTable('pos_points'), 500, 'No existe tabla pos_points.');

        $pos = DB::table('pos_points')
            ->where('id', $id)
            ->first();

        abort_if(! $pos, 404);

        $this->abortIfCannotAccessPos($pos);

        return $pos;
    }

    protected function authorizePos(object $pos): void
    {
        $user = auth()->user();

        abort_unless($user, 403);

        $isAdmin = (
            (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin())
            || (method_exists($user, 'isGroupAdmin') && $user->isGroupAdmin())
        );

        if ($isAdmin) {
            return;
        }

        if (Schema::hasTable('pos_point_user')) {
            $allowed = DB::table('pos_point_user')
                ->where('pos_point_id', $pos->id)
                ->where('user_id', $user->id)
                ->exists();

            abort_unless($allowed, 403);
        }
    }



    protected function v5487OpeningCashFromRequest(\Illuminate\Http\Request $request, object $cashier): array
    {
        $role = mb_strtolower(trim((string) ($cashier->role ?? '')));

        /*
         * Solo pedimos fondo inicial para cajero puro.
         * Vendedor o mixto no quedan obligados por ahora.
         */
        if ($role !== 'cashier') {
            return [
                'amount' => 0.0,
                'cash_count' => [],
            ];
        }

        $amount = round((float) $request->input('opening_amount', 0), 2);
        $cashCountRaw = $request->input('opening_cash_count', '[]');

        if (is_string($cashCountRaw)) {
            $decoded = json_decode($cashCountRaw, true);
            $cashCount = is_array($decoded) ? $decoded : [];
        } elseif (is_array($cashCountRaw)) {
            $cashCount = $cashCountRaw;
        } else {
            $cashCount = [];
        }

        $normalized = collect($cashCount)
            ->filter(fn ($row) => is_array($row))
            ->map(function ($row) {
                $value = round((float) ($row['value'] ?? 0), 2);
                $quantity = (int) ($row['quantity'] ?? 0);
                $type = (string) ($row['type'] ?? '');
                $name = (string) ($row['name'] ?? ('$' . number_format($value, 2)));

                return [
                    'name' => $name,
                    'type' => $type,
                    'value' => $value,
                    'quantity' => max(0, $quantity),
                    'total' => round($value * max(0, $quantity), 2),
                ];
            })
            ->filter(fn ($row) => $row['value'] > 0)
            ->values()
            ->all();

        $calculated = round((float) collect($normalized)->sum('total'), 2);

        return [
            'amount' => $calculated > 0 ? $calculated : max(0, $amount),
            'cash_count' => $normalized,
        ];
    }

    protected function v5487OpeningCashPayloadFromSessionNotes(?string $notes): array
    {
        $notes = (string) $notes;

        if ($notes === '' || ! str_contains($notes, '[APERTURA PDV]')) {
            return [];
        }

        foreach (preg_split('/\r\n|\r|\n/', $notes) as $line) {
            $line = trim((string) $line);

            if (! str_starts_with($line, '[APERTURA PDV]')) {
                continue;
            }

            $json = trim(substr($line, strlen('[APERTURA PDV]')));
            $decoded = json_decode($json, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }


    protected function v5487bOpeningCashFromRequest(\Illuminate\Http\Request $request): array
    {
        $cashCountRaw = $request->input('opening_cash_count', '[]');

        if (is_string($cashCountRaw)) {
            $decoded = json_decode($cashCountRaw, true);
            $cashCount = is_array($decoded) ? $decoded : [];
        } elseif (is_array($cashCountRaw)) {
            $cashCount = $cashCountRaw;
        } else {
            $cashCount = [];
        }

        $normalized = collect($cashCount)
            ->filter(fn ($row) => is_array($row))
            ->map(function ($row) {
                $value = round((float) ($row['value'] ?? 0), 2);
                $quantity = max(0, (int) ($row['quantity'] ?? 0));
                $total = round($value * $quantity, 2);

                return [
                    'name' => (string) ($row['name'] ?? ('$' . number_format($value, 2))),
                    'type' => (string) ($row['type'] ?? ''),
                    'value' => $value,
                    'quantity' => $quantity,
                    'total' => $total,
                ];
            })
            ->filter(fn ($row) => $row['value'] > 0)
            ->values()
            ->all();

        $calculated = round((float) collect($normalized)->sum('total'), 2);
        $amount = round((float) $request->input('opening_amount', 0), 2);

        return [
            'amount' => $calculated > 0 ? $calculated : max(0, $amount),
            'cash_count' => $normalized,
        ];
    }

    protected function v5487bOpeningCashPayloadFromSessionNotes(?string $notes): array
    {
        $notes = (string) $notes;

        if ($notes === '' || ! str_contains($notes, '[APERTURA PDV]')) {
            return [];
        }

        foreach (preg_split('/\r\n|\r|\n/', $notes) as $line) {
            $line = trim((string) $line);

            if (! str_starts_with($line, '[APERTURA PDV]')) {
                continue;
            }

            $json = trim(substr($line, strlen('[APERTURA PDV]')));
            $decoded = json_decode($json, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }


    protected function v5487dOpeningCashFromRequest(\Illuminate\Http\Request $request): array
    {
        $raw = $request->input('opening_cash_count', '[]');

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $rows = is_array($decoded) ? $decoded : [];
        } elseif (is_array($raw)) {
            $rows = $raw;
        } else {
            $rows = [];
        }

        $cashCount = collect($rows)
            ->filter(fn ($row) => is_array($row))
            ->map(function ($row) {
                $value = round((float) ($row['value'] ?? 0), 2);
                $quantity = max(0, (int) ($row['quantity'] ?? 0));
                $total = round($value * $quantity, 2);

                return [
                    'name' => (string) ($row['name'] ?? ('$' . number_format($value, 2))),
                    'type' => (string) ($row['type'] ?? ''),
                    'value' => $value,
                    'quantity' => $quantity,
                    'total' => $total,
                ];
            })
            ->filter(fn ($row) => $row['value'] > 0)
            ->values()
            ->all();

        $calculated = round((float) collect($cashCount)->sum('total'), 2);
        $amount = round((float) $request->input('opening_amount', 0), 2);

        return [
            'amount' => $calculated > 0 ? $calculated : max(0, $amount),
            'cash_count' => $cashCount,
        ];
    }

    protected function v5487dApplyOpeningCashToSession(int $sessionId, \Illuminate\Http\Request $request, ?object $cashier = null): void
    {
        if ($sessionId <= 0 || ! \Illuminate\Support\Facades\Schema::hasTable('pos_sessions')) {
            return;
        }

        $opening = $this->v5487dOpeningCashFromRequest($request);
        $amount = round((float) ($opening['amount'] ?? 0), 2);
        $cashCount = $opening['cash_count'] ?? [];

        /*
         * Si no viene nada desde el modal, no tocamos la sesión.
         */
        if ($amount <= 0 && empty($cashCount)) {
            return;
        }

        $session = \Illuminate\Support\Facades\DB::table('pos_sessions')
            ->where('id', $sessionId)
            ->first();

        if (! $session) {
            return;
        }

        $payload = [
            'opened_by_user_id' => auth()->id(),
            'opened_by_name' => auth()->user()?->name ?? auth()->user()?->email ?? ('Usuario #' . auth()->id()),
            'cashier_id' => (int) ($cashier->id ?? 0),
            'employee_id' => (int) ($cashier->employee_id ?? $session->employee_id ?? 0),
            'cashier_role' => (string) ($cashier->role ?? $session->staff_role ?? ''),
            'opening_amount' => $amount,
            'cash_count' => $cashCount,
            'opened_from' => 'pos_cashier_pin_modal',
            'applied_at' => now()->toDateTimeString(),
        ];

        $updates = [
            'opening_amount' => $amount,
            'updated_at' => now(),
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('pos_sessions', 'notes')) {
            $notes = (string) ($session->notes ?? '');
            $line = '[APERTURA PDV] ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            /*
             * Si ya existía una apertura vieja en 0, conservamos historial y agregamos la correcta.
             */
            $updates['notes'] = trim($notes) !== ''
                ? trim($notes) . "\n" . $line
                : $line;
        }

        \Illuminate\Support\Facades\DB::table('pos_sessions')
            ->where('id', $sessionId)
            ->update($updates);
    }

    protected function openSession(object $pos, object $cashier, array $openingCash = []): int
    {
        $employeeId = ! empty($cashier->employee_id)
            ? (int) $cashier->employee_id
            : null;

        $legacyCashierId = ! empty($cashier->legacy_cashier_id)
            ? (int) $cashier->legacy_cashier_id
            : (! empty($cashier->id) && empty($cashier->employee_id) ? (int) $cashier->id : 0);

        $query = DB::table('pos_sessions')
            ->where('pos_point_id', (int) $pos->id)
            ->where('status', 'open');

        if ($employeeId && Schema::hasColumn('pos_sessions', 'employee_id')) {
            $query->where('employee_id', $employeeId);
        } elseif ($legacyCashierId) {
            $query->where('pos_cashier_id', $legacyCashierId);
        }

        $existing = $query
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        $number = $this->nextSessionNumber((int) ($pos->company_id ?? 0));

        $openingAmount = round((float) ($openingCash['amount'] ?? 0), 2);
        $openingCashCount = $openingCash['cash_count'] ?? [];

        $openingPayload = [
            'opened_by_user_id' => auth()->id(),
            'opened_by_name' => auth()->user()?->name ?? auth()->user()?->email ?? ('Usuario #' . auth()->id()),
            'cashier_id' => (int) ($cashier->id ?? 0),
            'employee_id' => $employeeId,
            'cashier_role' => (string) ($cashier->role ?? ''),
            'opening_amount' => $openingAmount,
            'cash_count' => $openingCashCount,
            'opened_from' => 'pos_cashier_pin',
        ];

        $insert = [
            'company_id' => $pos->company_id ?? null,
            'pos_point_id' => $pos->id,
            'pos_cashier_id' => $legacyCashierId,
            'opened_by_user_id' => auth()->id(),
            'number' => $number,
            'status' => 'open',
            'opened_at' => now(),
            'opening_amount' => $openingAmount,
            'closing_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('pos_sessions', 'employee_id')) {
            $insert['employee_id'] = $employeeId;
        }

        if (Schema::hasColumn('pos_sessions', 'staff_role')) {
            $insert['staff_role'] = $cashier->role ?? null;
        }

        if (Schema::hasColumn('pos_sessions', 'notes')) {
            $insert['notes'] = '[APERTURA PDV] ' . json_encode($openingPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return DB::table('pos_sessions')->insertGetId($insert);
    }




    protected function nextSessionNumber(int $companyId): string
    {
        $prefix = 'TPV-' . now()->format('Ymd') . '-';

        $query = DB::table('pos_sessions')
            ->where('number', 'like', $prefix . '%');

        if ($companyId > 0) {
            $query->where('company_id', $companyId);
        }

        $last = $query->orderByDesc('number')->value('number');
        $next = 1;

        if ($last && preg_match('/-(\d+)$/', (string) $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    protected function configuredStockLocationId(object $pos): ?int
    {
        if (! empty($pos->stock_source_location_id)) {
            return (int) $pos->stock_source_location_id;
        }

        if (! empty($pos->stock_location_id)) {
            return (int) $pos->stock_location_id;
        }

        return null;
    }

    protected function defaultCustomer(object $pos): object
    {
        $default = (object) [
            'id' => null,
            'name' => 'Público en General',
            'rfc' => 'XAXX010101000',
            'email' => null,
            'phone' => null,
        ];

        if (! Schema::hasTable('contacts')) {
            return $default;
        }

        $contact = null;

        if (! empty($pos->default_customer_id)) {
            $contact = DB::table('contacts')
                ->where('id', $pos->default_customer_id)
                ->first();
        }

        if (! $contact) {
            $query = DB::table('contacts');

            if (! empty($pos->company_id) && Schema::hasColumn('contacts', 'company_id')) {
                $query->where('company_id', $pos->company_id);
            }

            $query->where(function ($q) {
                if (Schema::hasColumn('contacts', 'rfc')) {
                    $q->whereRaw('upper(rfc) = ?', ['XAXX010101000']);
                }

                foreach (['name', 'commercial_name', 'business_name', 'display_name', 'legal_name'] as $column) {
                    if (Schema::hasColumn('contacts', $column)) {
                        $q->orWhereRaw("lower(coalesce({$column}, '')) like ?", ['%publico%general%']);
                        $q->orWhereRaw("lower(coalesce({$column}, '')) like ?", ['%público%general%']);
                    }
                }
            });

            $contact = $query->orderBy('id')->first();
        }

        if (! $contact) {
            return $default;
        }

        $name = null;

        foreach (['name', 'commercial_name', 'business_name', 'display_name', 'legal_name'] as $column) {
            if (isset($contact->{$column}) && trim((string) $contact->{$column}) !== '') {
                $name = trim((string) $contact->{$column});
                break;
            }
        }

        return (object) [
            'id' => $contact->id,
            'name' => $name ?: 'Público en General',
            'rfc' => $contact->rfc ?? 'XAXX010101000',
            'email' => $contact->email ?? null,
            'phone' => $contact->phone ?? null,
        ];
    }

    protected function realCategories(object $pos): array
    {
        $fallback = [
            ['id' => null, 'name' => 'Todas', 'color' => '#2563eb', 'icon' => '▦'],
        ];

        if (! Schema::hasTable('product_categories')) {
            return $fallback;
        }

        $query = DB::table('product_categories');

        if (! empty($pos->company_id) && Schema::hasColumn('product_categories', 'company_id')) {
            $query->where('company_id', $pos->company_id);
        }

        if (Schema::hasColumn('product_categories', 'is_active')) {
            $query->where('is_active', true);
        }

        $allowed = $this->jsonArray($pos->allowed_category_ids ?? null, []);

        if (! empty($pos->restrict_categories) && $allowed) {
            $query->whereIn('id', array_map('intval', $allowed));
        }

        $colors = ['#2563eb', '#16a34a', '#f97316', '#7c3aed', '#ec4899', '#0d9488', '#0891b2', '#9333ea'];
        $icons = ['▦', '🏷️', '🛒', '📦', '⭐', '🧾', '🛍️', '🔖'];

        $rows = $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(12)
            ->get();

        $categories = [
            ['id' => null, 'name' => 'Todas', 'color' => '#2563eb', 'icon' => '▦'],
        ];

        foreach ($rows as $index => $row) {
            $categories[] = [
                'id' => $row->id,
                'name' => $row->name ?: ('Categoría ' . $row->id),
                'color' => $colors[($index + 1) % count($colors)],
                'icon' => $icons[($index + 1) % count($icons)],
            ];
        }

        return $categories;
    }

    protected function realProducts(object $pos): array
    {
        if (! Schema::hasTable('products')) {
            return [];
        }

        $limit = (int) ($pos->loaded_products_limit ?? 500);
        // BEXIA_V5828B5C_CATALOG_LIMIT
        // Permite considerar el catalogo completo de empresas medianas.
        $limit = max(20, min($limit, 5000));

        $query = DB::table('products');

        if (! empty($pos->company_id) && Schema::hasColumn('products', 'company_id')) {
            $query->where('company_id', $pos->company_id);
        }

        if (Schema::hasColumn('products', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('products', 'can_be_sold')) {
            $query->where('can_be_sold', true);
        }

        if (Schema::hasColumn('products', 'available_in_pos')) {
            $query->where(function ($q) {
                $q->where('available_in_pos', true)
                    ->orWhereNull('available_in_pos');
            });
        }

        // BEXIA_V5820E2A1_REAL_PRODUCTS_FILTER_ZERO_PRICE_QUERY
        // El POS solo debe cargar productos con precio base valido.
        // No se modifica el catalogo ni available_in_pos.
        if (Schema::hasColumn('products', 'sale_price')) {
            $query->whereNotNull('sale_price')
                ->where('sale_price', '>', 0);
        }

        $allowed = $this->jsonArray($pos->allowed_category_ids ?? null, []);

        if (! empty($pos->restrict_categories) && $allowed && Schema::hasColumn('products', 'product_category_id')) {
            $query->whereIn('product_category_id', array_map('intval', $allowed));
        }

        if (! empty($pos->initial_category_id) && empty($pos->restrict_categories) && Schema::hasColumn('products', 'product_category_id')) {
            // No filtra por la inicial; solo se usa como categoría seleccionada visualmente en versiones posteriores.
        }

        // BEXIA_V5828B5C_STOCK_CANDIDATES_BEFORE_LIMIT
        // El limite debe aplicarse después de reducir el catálogo a productos
        // relacionados con existencia, series o venta permitida sin stock.
        $v5828b5cCandidateIds = collect();

        $v5828b5cCompanyId = (int) ($pos->company_id ?? 0);
        $v5828b5cWarehouseId = (int) ($pos->warehouse_id ?? 0);
        $v5828b5cLocationId =
            (int) ($this->configuredStockLocationId($pos) ?? 0);

        $v5828b5cCurrentWarehouseScope =
            (string) ($pos->stock_scope ?? 'current_warehouse')
                === 'current_warehouse';

        if (
            Schema::hasTable('stock_quants')
            && Schema::hasColumn('stock_quants', 'product_id')
        ) {
            $v5828b5cStockQuery = DB::table('stock_quants')
                ->whereNotNull('product_id');

            if (
                Schema::hasColumn('stock_quants', 'quantity')
            ) {
                $v5828b5cStockQuery->where('quantity', '>', 0);
            }

            if (
                $v5828b5cCompanyId > 0
                && Schema::hasColumn('stock_quants', 'company_id')
            ) {
                $v5828b5cStockQuery->where(
                    'company_id',
                    $v5828b5cCompanyId
                );
            }

            if (
                $v5828b5cCurrentWarehouseScope
                && $v5828b5cWarehouseId > 0
                && Schema::hasColumn('stock_quants', 'warehouse_id')
            ) {
                $v5828b5cStockQuery->where(
                    'warehouse_id',
                    $v5828b5cWarehouseId
                );
            }

            if (
                $v5828b5cCurrentWarehouseScope
                && $v5828b5cLocationId > 0
                && Schema::hasColumn('stock_quants', 'location_id')
            ) {
                $v5828b5cStockQuery->where(
                    'location_id',
                    $v5828b5cLocationId
                );
            }

            $v5828b5cHasQuantVariant =
                Schema::hasColumn(
                    'stock_quants',
                    'product_variant_id'
                );

            $v5828b5cQuantColumns = ['product_id'];

            if ($v5828b5cHasQuantVariant) {
                $v5828b5cQuantColumns[] = 'product_variant_id';
            }

            $v5828b5cQuantIds = $v5828b5cStockQuery
                ->get($v5828b5cQuantColumns)
                ->map(function ($row) use (
                    $v5828b5cHasQuantVariant
                ): int {
                    $variantId = $v5828b5cHasQuantVariant
                        ? (int) ($row->product_variant_id ?? 0)
                        : 0;

                    return $variantId > 0
                        ? $variantId
                        : (int) ($row->product_id ?? 0);
                });

            $v5828b5cCandidateIds =
                $v5828b5cCandidateIds->merge(
                    $v5828b5cQuantIds
                );
        }

        if (
            Schema::hasTable('stock_serial_numbers')
            && Schema::hasColumn(
                'stock_serial_numbers',
                'product_id'
            )
        ) {
            $v5828b5cSerialQuery =
                DB::table('stock_serial_numbers')
                    ->whereNotNull('product_id');

            if (
                Schema::hasColumn(
                    'stock_serial_numbers',
                    'status'
                )
            ) {
                $v5828b5cSerialQuery->where(
                    'status',
                    'available'
                );
            }

            if (
                $v5828b5cCompanyId > 0
                && Schema::hasColumn(
                    'stock_serial_numbers',
                    'company_id'
                )
            ) {
                $v5828b5cSerialQuery->where(
                    'company_id',
                    $v5828b5cCompanyId
                );
            }

            if (
                $v5828b5cCurrentWarehouseScope
                && $v5828b5cWarehouseId > 0
                && Schema::hasColumn(
                    'stock_serial_numbers',
                    'current_warehouse_id'
                )
            ) {
                $v5828b5cSerialQuery->where(
                    'current_warehouse_id',
                    $v5828b5cWarehouseId
                );
            }

            if (
                $v5828b5cCurrentWarehouseScope
                && $v5828b5cLocationId > 0
                && Schema::hasColumn(
                    'stock_serial_numbers',
                    'current_location_id'
                )
            ) {
                $v5828b5cSerialQuery->where(
                    'current_location_id',
                    $v5828b5cLocationId
                );
            }

            $v5828b5cHasSerialVariant =
                Schema::hasColumn(
                    'stock_serial_numbers',
                    'product_variant_id'
                );

            $v5828b5cSerialColumns = ['product_id'];

            if ($v5828b5cHasSerialVariant) {
                $v5828b5cSerialColumns[] =
                    'product_variant_id';
            }

            $v5828b5cSerialIds = $v5828b5cSerialQuery
                ->get($v5828b5cSerialColumns)
                ->map(function ($row) use (
                    $v5828b5cHasSerialVariant
                ): int {
                    $variantId = $v5828b5cHasSerialVariant
                        ? (int) (
                            $row->product_variant_id ?? 0
                        )
                        : 0;

                    return $variantId > 0
                        ? $variantId
                        : (int) ($row->product_id ?? 0);
                });

            $v5828b5cCandidateIds =
                $v5828b5cCandidateIds->merge(
                    $v5828b5cSerialIds
                );
        }

        $v5828b5cCandidateIds = $v5828b5cCandidateIds
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $v5828b5cProductColumns =
            Schema::getColumnListing('products');

        $query->where(function ($candidateQuery) use (
            $v5828b5cCandidateIds,
            $v5828b5cProductColumns
        ): void {
            if ($v5828b5cCandidateIds->isNotEmpty()) {
                $candidateQuery->whereIn(
                    'id',
                    $v5828b5cCandidateIds->all()
                );
            } else {
                $candidateQuery->whereRaw('1 = 0');
            }

            if (
                in_array(
                    'product_type',
                    $v5828b5cProductColumns,
                    true
                )
            ) {
                $candidateQuery->orWhere(
                    'product_type',
                    'service'
                );
            }

            if (
                in_array(
                    'allow_out_of_stock_sales',
                    $v5828b5cProductColumns,
                    true
                )
            ) {
                $candidateQuery->orWhere(
                    'allow_out_of_stock_sales',
                    true
                );
            }
        });

        $rows = $query
            ->orderBy('name')
            ->limit($limit)
            ->get();

        // BEXIA_V5828B5C_BULK_POS_STOCK
        // Evita ejecutar una consulta de existencia por cada producto.
        $v5828b5BulkStock = $this->v5828b5BulkStockForProducts(
            $rows,
            $pos
        );

        $products = [];

        foreach ($rows as $row) {
            $stock = $v5828b5BulkStock->get((int) $row->id);

            // Fallback defensivo para estructuras especiales no contempladas.
            if ($stock === null) {
                $stock = $this->stockForProduct($row, $pos);
            }

            $v5828b5ProductType =
                (string) ($row->product_type ?? 'stockable');

            $v5828b5IsService =
                $v5828b5ProductType === 'service';

            $v5828b5ProductAllowsOutOfStock =
                (bool) ($row->allow_out_of_stock_sales ?? false);

            // BEXIA_V5828B5C_FILTER_STOCK_BEFORE_RENDER
            // La vista ya descartaba estas filas. Ahora se eliminan antes de
            // generar cientos de tarjetas y HTML innecesario.
            if (
                ! (bool) ($pos->allow_out_of_stock_sales ?? false)
                && ! $v5828b5IsService
                && ! $v5828b5ProductAllowsOutOfStock
                && (float) $stock
                    <= (float) ($pos->deny_sale_below_qty ?? 0)
            ) {
                continue;
            }

            $price = $this->productPrice($row);

            // BEXIA_V5820E2A1_REAL_PRODUCTS_SKIP_ZERO_PRICE
            // Doble candado para no renderizar productos sin precio valido.
            if ($price <= 0) {
                continue;
            }

            $products[] = [
                'id' => (int) $row->id,
                'name' => $this->productDisplayName($row),
                'code' => (string) ($row->barcode ?: ($row->sku ?: ($row->internal_reference ?: ''))),
                'price' => $price,
                'stock' => $stock,
                'category_id' => $row->product_category_id ?? null,
                'image_path' => $row->image_path ?? null,
                'can_sell' => ((bool) ($pos->allow_out_of_stock_sales ?? false)) || $stock > (float) ($pos->deny_sale_below_qty ?? 0),
                'tracking' => $row->tracking ?? 'none',
            ];
        }

        // V5.45.1B enrich POS product metadata: servicios, favoritos y venta sin stock.
        $v5451ProductIds = collect($products)
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($v5451ProductIds->isNotEmpty() && Schema::hasTable('products')) {
            $v5451Columns = Schema::getColumnListing('products');

            $v5451Select = collect([
                'id',
                'product_type',
                'product_category_id',
                'allow_out_of_stock_sales',
                'available_in_pos',
                'can_be_sold',
                'is_pos_favorite',
            ])->filter(fn ($column) => in_array($column, $v5451Columns, true))
                ->values()
                ->all();

            $v5451ProductMeta = DB::table('products')
                ->whereIn('id', $v5451ProductIds)
                ->get($v5451Select)
                ->keyBy('id');

            foreach ($products as &$v5451Product) {
                $v5451Id = (int) ($v5451Product['id'] ?? 0);
                $v5451Row = $v5451ProductMeta->get($v5451Id);

                if (! $v5451Row) {
                    continue;
                }

                $v5451ProductType = (string) ($v5451Row->product_type ?? ($v5451Product['product_type'] ?? 'stockable'));
                $v5451IsService = $v5451ProductType === 'service';
                $v5451Stock = (float) ($v5451Product['stock'] ?? 0);
                $v5451ProductAllowsOutOfStock = (bool) ($v5451Row->allow_out_of_stock_sales ?? false);

                $v5451Product['product_type'] = $v5451ProductType;
                $v5451Product['is_service'] = $v5451IsService;
                $v5451Product['is_pos_favorite'] = (bool) ($v5451Row->is_pos_favorite ?? false);

                if (empty($v5451Product['category_id']) && isset($v5451Row->product_category_id)) {
                    $v5451Product['category_id'] = $v5451Row->product_category_id;
                }

                if ($v5451IsService || $v5451ProductAllowsOutOfStock) {
                    $v5451Product['can_sell'] = true;
                } elseif (! (bool) ($pos->allow_out_of_stock_sales ?? false)) {
                    $v5451Product['can_sell'] = $v5451Stock > (float) ($pos->deny_sale_below_qty ?? 0);
                }
            }

            unset($v5451Product);
        }

        return $products;
    }

    /**
     * BEXIA_V5828B5C_BULK_POS_STOCK
     *
     * Calcula en bloque la existencia disponible para la carga inicial del PDV.
     * Conserva soporte para:
     * - productos simples;
     * - variantes guardadas en products;
     * - productos con numeros de serie;
     * - servicios;
     * - empresa, almacen y ubicacion configurados.
     */
    protected function v5828b5BulkStockForProducts(
        $rows,
        object $pos
    ): \Illuminate\Support\Collection {
        $rows = collect($rows)->values();
        $result = collect();

        if ($rows->isEmpty()) {
            return $result;
        }

        $companyId = (int) ($pos->company_id ?? 0);
        $warehouseId = (int) ($pos->warehouse_id ?? 0);
        $locationId = (int) $this->configuredStockLocationId($pos);

        $pairs = $rows
            ->map(function ($row): array {
                $parentId = ! empty($row->parent_product_id)
                    ? (int) $row->parent_product_id
                    : 0;

                return [
                    'row_id' => (int) $row->id,
                    'product_id' =>
                        $parentId > 0 ? $parentId : (int) $row->id,
                    'variant_id' =>
                        $parentId > 0 ? (int) $row->id : 0,
                ];
            })
            ->filter(
                fn (array $pair): bool =>
                    $pair['row_id'] > 0
                    && $pair['product_id'] > 0
            )
            ->values();

        $productIds = $pairs
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $stockByKey = collect();

        if (
            $productIds->isNotEmpty()
            && Schema::hasTable('stock_quants')
            && Schema::hasColumn('stock_quants', 'product_id')
        ) {
            $stockQuery = DB::table('stock_quants')
                ->whereIn('product_id', $productIds);

            if (
                $companyId > 0
                && Schema::hasColumn('stock_quants', 'company_id')
            ) {
                $stockQuery->where('company_id', $companyId);
            }

            if (
                $warehouseId > 0
                && Schema::hasColumn('stock_quants', 'warehouse_id')
            ) {
                $stockQuery->where('warehouse_id', $warehouseId);
            }

            if (
                $locationId > 0
                && Schema::hasColumn('stock_quants', 'location_id')
            ) {
                $stockQuery->where('location_id', $locationId);
            }

            $quantityExpression =
                Schema::hasColumn('stock_quants', 'quantity')
                    ? 'SUM(quantity)'
                    : '0';

            $reservedExpression =
                Schema::hasColumn('stock_quants', 'reserved_quantity')
                    ? 'SUM(reserved_quantity)'
                    : '0';

            $hasVariantColumn =
                Schema::hasColumn(
                    'stock_quants',
                    'product_variant_id'
                );

            $variantSelect = $hasVariantColumn
                ? 'product_variant_id'
                : 'NULL as product_variant_id';

            $stockQuery->selectRaw(
                "product_id,
                 {$variantSelect},
                 {$quantityExpression} as quantity,
                 {$reservedExpression} as reserved_quantity"
            );

            $stockQuery->groupBy('product_id');

            if ($hasVariantColumn) {
                $stockQuery->groupBy('product_variant_id');
            }

            $stockByKey = $stockQuery
                ->get()
                ->keyBy(function ($row): string {
                    return ((int) $row->product_id)
                        . ':'
                        . ((int) ($row->product_variant_id ?? 0));
                });
        }

        $parentIds = $rows
            ->pluck('parent_product_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $parentTracking = collect();

        if (
            $parentIds->isNotEmpty()
            && Schema::hasTable('products')
        ) {
            $columns = Schema::getColumnListing('products');

            $select = ['id'];

            foreach (['tracking', 'advanced_tracking_mode'] as $column) {
                if (in_array($column, $columns, true)) {
                    $select[] = $column;
                }
            }

            $parentTracking = DB::table('products')
                ->whereIn('id', $parentIds)
                ->get($select)
                ->keyBy('id');
        }

        $serialByKey = collect();

        if (
            Schema::hasTable('stock_serial_numbers')
            && Schema::hasColumn(
                'stock_serial_numbers',
                'product_id'
            )
        ) {
            $serialQuery = DB::table('stock_serial_numbers')
                ->whereIn('product_id', $productIds);

            if (
                Schema::hasColumn(
                    'stock_serial_numbers',
                    'status'
                )
            ) {
                $serialQuery->where('status', 'available');
            }

            if (
                $companyId > 0
                && Schema::hasColumn(
                    'stock_serial_numbers',
                    'company_id'
                )
            ) {
                $serialQuery->where('company_id', $companyId);
            }

            if (
                $warehouseId > 0
                && Schema::hasColumn(
                    'stock_serial_numbers',
                    'current_warehouse_id'
                )
            ) {
                $serialQuery->where(
                    'current_warehouse_id',
                    $warehouseId
                );
            }

            if (
                $locationId > 0
                && Schema::hasColumn(
                    'stock_serial_numbers',
                    'current_location_id'
                )
            ) {
                $serialQuery->where(
                    'current_location_id',
                    $locationId
                );
            }

            $hasSerialVariant =
                Schema::hasColumn(
                    'stock_serial_numbers',
                    'product_variant_id'
                );

            $variantSelect = $hasSerialVariant
                ? 'product_variant_id'
                : 'NULL as product_variant_id';

            $serialQuery->selectRaw(
                "product_id,
                 {$variantSelect},
                 COUNT(*) as available_serials"
            );

            $serialQuery->groupBy('product_id');

            if ($hasSerialVariant) {
                $serialQuery->groupBy('product_variant_id');
            }

            $serialByKey = $serialQuery
                ->get()
                ->keyBy(function ($row): string {
                    return ((int) $row->product_id)
                        . ':'
                        . ((int) ($row->product_variant_id ?? 0));
                });
        }

        foreach ($rows as $row) {
            $rowId = (int) ($row->id ?? 0);

            if ($rowId <= 0) {
                continue;
            }

            $productType =
                (string) ($row->product_type ?? 'stockable');

            if ($productType === 'service') {
                $result->put($rowId, 999999.0);
                continue;
            }

            $parentId = ! empty($row->parent_product_id)
                ? (int) $row->parent_product_id
                : 0;

            $productId = $parentId > 0
                ? $parentId
                : $rowId;

            $variantId = $parentId > 0
                ? $rowId
                : 0;

            $key = $productId . ':' . $variantId;

            $trackingValues = [
                (string) ($row->tracking ?? ''),
                (string) ($row->advanced_tracking_mode ?? ''),
            ];

            if ($parentId > 0) {
                $parent = $parentTracking->get($parentId);

                if ($parent) {
                    $trackingValues[] =
                        (string) ($parent->tracking ?? '');

                    $trackingValues[] =
                        (string) (
                            $parent->advanced_tracking_mode ?? ''
                        );
                }
            }

            $usesSerial = collect($trackingValues)
                ->map(
                    fn ($value) =>
                        mb_strtolower(trim((string) $value))
                )
                ->contains(function ($value): bool {
                    return $value !== ''
                        && (
                            str_contains($value, 'serial')
                            || str_contains($value, 'serie')
                        );
                });

            if ($usesSerial && $serialByKey->has($key)) {
                $result->put(
                    $rowId,
                    (float) (
                        $serialByKey
                            ->get($key)
                            ->available_serials ?? 0
                    )
                );

                continue;
            }

            $stock = $stockByKey->get($key);

            $quantity = (float) ($stock->quantity ?? 0);
            $reserved = (float) ($stock->reserved_quantity ?? 0);

            $result->put(
                $rowId,
                round($quantity - $reserved, 4)
            );
        }

        return $result;
    }

    protected function productDisplayName(object $product): string
    {
        $name = trim((string) ($product->name ?? ''));

        if (! empty($product->variant_name)) {
            $name .= ' / ' . trim((string) $product->variant_name);
        }

        return $name !== '' ? $name : ('Producto #' . $product->id);
    }

    protected function productPrice(object $product): float
    {
        foreach (['sale_price', 'list_price', 'price'] as $column) {
            if (isset($product->{$column}) && is_numeric($product->{$column})) {
                return (float) $product->{$column};
            }
        }

        return 0.0;
    }

    protected function stockForProduct(object $product, object $pos): float
    {
        $productType = (string) ($product->product_type ?? 'stockable');

        if ($productType === 'service') {
            return 999999.0;
        }

        // BEXIA_V5545H_STOCK_INITIAL_CARD_SERIAL_VARIANT
        // En variantes, el stock real se guarda como:
        // stock_quants.product_id = producto padre
        // stock_quants.product_variant_id = variante
        // stock_serial_numbers.product_id = producto padre
        // stock_serial_numbers.product_variant_id = variante
        $parentProductId = ! empty($product->parent_product_id) ? (int) $product->parent_product_id : 0;
        $productIdForStock = $parentProductId > 0 ? $parentProductId : (int) $product->id;
        $variantIdForStock = $parentProductId > 0 ? (int) $product->id : null;

        $locationId = $this->configuredStockLocationId($pos);

        $usesSerialTracking = false;

        foreach (['tracking', 'advanced_tracking_mode'] as $column) {
            $value = strtolower(trim((string) ($product->{$column} ?? '')));

            if ($value !== '' && (str_contains($value, 'serial') || str_contains($value, 'serie'))) {
                $usesSerialTracking = true;
            }
        }

        if (
            ! $usesSerialTracking
            && $parentProductId > 0
            && Schema::hasTable('products')
        ) {
            $parent = DB::table('products')
                ->where('id', $parentProductId)
                ->first();

            if ($parent) {
                foreach (['tracking', 'advanced_tracking_mode'] as $column) {
                    $value = strtolower(trim((string) ($parent->{$column} ?? '')));

                    if ($value !== '' && (str_contains($value, 'serial') || str_contains($value, 'serie'))) {
                        $usesSerialTracking = true;
                    }
                }
            }
        }

        if (
            $usesSerialTracking
            && Schema::hasTable('stock_serial_numbers')
            && Schema::hasColumn('stock_serial_numbers', 'product_id')
        ) {
            $serialQuery = DB::table('stock_serial_numbers')
                ->where('product_id', $productIdForStock)
                ->where('status', 'available');

            if (! empty($pos->company_id) && Schema::hasColumn('stock_serial_numbers', 'company_id')) {
                $serialQuery->where('company_id', $pos->company_id);
            }

            if (
                $variantIdForStock
                && Schema::hasColumn('stock_serial_numbers', 'product_variant_id')
            ) {
                $serialQuery->where('product_variant_id', $variantIdForStock);
            }

            if (
                ($pos->stock_scope ?? 'current_warehouse') === 'current_warehouse'
                && ! empty($pos->warehouse_id)
                && Schema::hasColumn('stock_serial_numbers', 'current_warehouse_id')
            ) {
                $serialQuery->where('current_warehouse_id', $pos->warehouse_id);
            }

            if (
                $locationId
                && ($pos->stock_scope ?? 'current_warehouse') === 'current_warehouse'
                && Schema::hasColumn('stock_serial_numbers', 'current_location_id')
            ) {
                $serialQuery->where('current_location_id', $locationId);
            }

            return (float) $serialQuery->count();
        }

        if (! Schema::hasTable('stock_quants')) {
            return 0.0;
        }

        $query = DB::table('stock_quants')
            ->where('product_id', $productIdForStock);

        if (
            $variantIdForStock
            && Schema::hasColumn('stock_quants', 'product_variant_id')
        ) {
            $query->where('product_variant_id', $variantIdForStock);
        }

        if (! empty($pos->company_id) && Schema::hasColumn('stock_quants', 'company_id')) {
            $query->where('company_id', $pos->company_id);
        }

        if (($pos->stock_scope ?? 'current_warehouse') === 'current_warehouse' && ! empty($pos->warehouse_id)) {
            $query->where('warehouse_id', $pos->warehouse_id);
        }

        if ($locationId && ($pos->stock_scope ?? 'current_warehouse') === 'current_warehouse') {
            $query->where('location_id', $locationId);
        }

        $quantity = (float) (clone $query)->sum('quantity');

        $reservedFromQuant = Schema::hasColumn('stock_quants', 'reserved_quantity')
            ? (float) (clone $query)->sum('reserved_quantity')
            : 0.0;

        $reservedFromReservations = 0.0;

        if (Schema::hasTable('stock_reservations')) {
            $reservationQuery = DB::table('stock_reservations')
                ->where('product_id', $productIdForStock)
                ->where('status', 'active');

            if (
                $variantIdForStock
                && Schema::hasColumn('stock_reservations', 'product_variant_id')
            ) {
                $reservationQuery->where('product_variant_id', $variantIdForStock);
            }

            if (! empty($pos->company_id) && Schema::hasColumn('stock_reservations', 'company_id')) {
                $reservationQuery->where('company_id', $pos->company_id);
            }

            if (($pos->stock_scope ?? 'current_warehouse') === 'current_warehouse' && ! empty($pos->warehouse_id)) {
                $reservationQuery->where('warehouse_id', $pos->warehouse_id);
            }

            if ($locationId && ($pos->stock_scope ?? 'current_warehouse') === 'current_warehouse') {
                $reservationQuery->where('location_id', $locationId);
            }

            $reservedFromReservations = (float) $reservationQuery->sum('quantity');
        }

        $reserved = max($reservedFromQuant, $reservedFromReservations);

        return max(0.0, round($quantity - $reserved, 6));
    }


    protected function paymentMethodsForPos(object $pos): array
    {
        $ids = $this->jsonArray($pos->payment_method_ids ?? null, []);

        if ($ids) {
            foreach ($this->paymentMethodTables() as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $columns = Schema::getColumnListing($table);

                $rows = DB::table($table)
                    ->whereIn('id', $ids)
                    ->get();

                if ($rows->isNotEmpty()) {
                    return $rows
                        ->map(function ($row) use ($columns) {
                            $parts = [];

                            foreach (['code', 'name', 'description', 'display_name', 'title'] as $column) {
                                if (in_array($column, $columns, true) && isset($row->{$column}) && trim((string) $row->{$column}) !== '') {
                                    $parts[] = trim((string) $row->{$column});
                                }
                            }

                            return $parts ? implode(' - ', array_unique($parts)) : ('Método #' . $row->id);
                        })
                        ->values()
                        ->all();
                }
            }
        }

        $names = $this->jsonArray($pos->payment_method_names ?? null, []);

        return $names ?: ['Efectivo', 'Tarjeta', 'Transferencia'];
    }

    protected function paymentMethodTables(): array
    {
        $candidates = [
            'sat_payment_forms',
            'sat_payment_form',
            'payment_forms',
            'payment_form_catalogs',
            'cfdi_payment_forms',
            'fiscal_payment_forms',
            'sat_payment_methods',
            'payment_methods',
        ];

        try {
            $dynamic = collect(DB::select("
                select table_name
                from information_schema.tables
                where table_schema = current_schema()
                  and (
                    table_name ilike '%payment%form%'
                    or table_name ilike '%payment%method%'
                    or table_name ilike '%forma%pago%'
                    or table_name ilike '%metodo%pago%'
                    or table_name ilike '%sat%payment%'
                  )
                order by table_name
            "))
                ->pluck('table_name')
                ->all();

            $candidates = array_values(array_unique(array_merge($candidates, $dynamic)));
        } catch (\Throwable $e) {
            //
        }

        return $candidates;
    }

    protected function ticketLogoUrl(object $pos): ?string
    {
        $path = trim((string) ($pos->ticket_logo_path ?? ''));

        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset('storage/' . ltrim($path, '/'));
    }


    protected function jsonArray(mixed $value, array $default = []): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return array_values(array_filter($decoded, fn ($item) => $item !== null && $item !== ''));
            }
        }

        return $default;
    }

    protected function labelFromTable(string $table, mixed $id): string
    {
        if (! $id || ! Schema::hasTable($table)) {
            return '—';
        }

        $row = DB::table($table)
            ->where('id', $id)
            ->first();

        if (! $row) {
            return '—';
        }

        $parts = [];

        foreach (['name', 'code'] as $column) {
            if (isset($row->{$column}) && trim((string) $row->{$column}) !== '') {
                $parts[] = trim((string) $row->{$column});
            }
        }

        return $parts ? implode(' - ', $parts) : ('#' . $id);
    }

    protected function v5327bCanManageAllPos(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        foreach (['isSystemAdmin', 'isGroupAdmin', 'isCompanyAdmin', 'isAdmin'] as $method) {
            if (method_exists($user, $method) && $user->{$method}()) {
                return true;
            }
        }

        foreach (['role', 'type', 'role_name'] as $attribute) {
            $value = strtolower((string) ($user->{$attribute} ?? ''));

            if (in_array($value, [
                'super_admin',
                'system_admin',
                'admin',
                'administrator',
                'company_admin',
                'group_admin',
                'admin_empresa',
                'admin_grupo',
                'super administrador',
                'administrador',
            ], true)) {
                return true;
            }
        }

        try {
            if (method_exists($user, 'roles')) {
                $roles = $user->roles()->pluck('name')->map(fn ($name) => strtolower((string) $name))->all();

                foreach ($roles as $role) {
                    if (in_array($role, [
                        'super_admin',
                        'system_admin',
                        'admin',
                        'administrator',
                        'company_admin',
                        'group_admin',
                        'admin_empresa',
                        'admin_grupo',
                        'super administrador',
                        'administrador',
                    ], true)) {
                        return true;
                    }
                }
            }
        } catch (\Throwable $e) {
            //
        }

        if (method_exists($user, 'can')) {
            foreach ([
                'company.update',
                'pos.manage',
                'pos.view_all',
                'pos_points.view_all',
                'punto_de_venta.manage',
                'punto_de_venta.view_all',
            ] as $permission) {
                try {
                    if ($user->can($permission)) {
                        return true;
                    }
                } catch (\Throwable $e) {
                    //
                }
            }
        }

        return false;
    }

    protected function v5327bAssignedPosPointIdsForCurrentUser(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        $ids = collect();

        if (
            \Illuminate\Support\Facades\Schema::hasTable('employees')
            && \Illuminate\Support\Facades\Schema::hasTable('pos_point_employee')
        ) {
            $employeeQuery = \Illuminate\Support\Facades\DB::table('employees');

            if (\Illuminate\Support\Facades\Schema::hasColumn('employees', 'user_id')) {
                $employeeQuery->where('user_id', $user->id);
            } else {
                $employeeQuery->whereRaw('1 = 0');
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn('employees', 'pos_active')) {
                $employeeQuery->where('pos_active', true);
            }

            $employeeIds = $employeeQuery->pluck('id')->values()->all();

            if ($employeeIds) {
                $assigned = \Illuminate\Support\Facades\DB::table('pos_point_employee')
                    ->whereIn('employee_id', $employeeIds)
                    ->where('is_active', true)
                    ->pluck('pos_point_id');

                $ids = $ids->merge($assigned);
            }
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('pos_cashiers')) {
            $legacyQuery = \Illuminate\Support\Facades\DB::table('pos_cashiers');

            if (\Illuminate\Support\Facades\Schema::hasColumn('pos_cashiers', 'user_id')) {
                $legacyQuery->where('user_id', $user->id);
            } else {
                $legacyQuery->whereRaw('1 = 0');
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn('pos_cashiers', 'is_active')) {
                $legacyQuery->where('is_active', true);
            }

            $ids = $ids->merge($legacyQuery->pluck('pos_point_id'));
        }

        return $ids
            ->filter()
            ->unique()
            ->values()
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function v5327bAbortIfCannotAccessPos(object $pos): void
    {
        if ($this->v5327bCanManageAllPos()) {
            return;
        }

        $assignedIds = $this->v5327bAssignedPosPointIdsForCurrentUser();

        abort_unless(in_array((int) $pos->id, $assignedIds, true), 403);
    }




    protected function v5514bUserCanAnyPosPermission(array $permissions): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin()) {
            return true;
        }

        if (method_exists($user, 'isGroupAdmin') && $user->isGroupAdmin()) {
            return true;
        }

        foreach ($permissions as $permission) {
            if (method_exists($user, 'can') && $user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    protected function v5514bCanClosePosSession(): bool
    {
        return $this->v5514bUserCanAnyPosPermission([
            'pos.session.close',

            // Compatibilidad temporal por si existía otro permiso previo.
            'pos.sessions.close',
            'pos.close_session',
            'pos.session_close',
        ]);
    }

    protected function v5514bCanCancelPendingPosTicket(): bool
    {
        return $this->v5514bUserCanAnyPosPermission([
            'pos.ticket.cancel_pending',

            // Compatibilidad temporal con permiso anterior encontrado en código.
            'pos.pending_tickets.cancel',
        ]);
    }

    protected function v5514bForbiddenPosResponse(\Illuminate\Http\Request $request, string $message)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => false,
                'success' => false,
                'message' => $message,
            ], 403);
        }

        return back()->with('error', $message);
    }


    protected function v5515aWritePosAuditLog(string $action, array $payload = []): void
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('pos_audit_logs')) {
                return;
            }

            $request = request();

            $companyId = $payload['company_id'] ?? null;

            if (! $companyId && function_exists('tenant')) {
                try {
                    $tenant = tenant();

                    if (is_object($tenant) && isset($tenant->id)) {
                        $companyId = (int) $tenant->id;
                    }
                } catch (\Throwable $e) {
                    //
                }
            }

            \Illuminate\Support\Facades\DB::table('pos_audit_logs')->insert([
                'company_id' => $companyId,
                // V5.51.5L - usar auth()->id(), o payload user_id si auth no está disponible.
                'user_id' => auth()->id() ?: ($payload['user_id'] ?? null),

                'pos_session_id' => $payload['pos_session_id'] ?? null,
                'pos_order_id' => $payload['pos_order_id'] ?? null,
                'pos_order_refund_id' => $payload['pos_order_refund_id'] ?? null,
                'stock_movement_id' => $payload['stock_movement_id'] ?? null,

                'action' => $action,
                'entity_type' => $payload['entity_type'] ?? null,
                'entity_id' => $payload['entity_id'] ?? null,

                'description' => $payload['description'] ?? null,

                'before_data' => isset($payload['before_data'])
                    ? json_encode($payload['before_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null,

                'after_data' => isset($payload['after_data'])
                    ? json_encode($payload['after_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null,

                'metadata' => isset($payload['metadata'])
                    ? json_encode($payload['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null,

                'ip_address' => $request?->ip(),
                'user_agent' => substr((string) $request?->userAgent(), 0, 2000),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('No se pudo escribir auditoría PDV.', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }



    protected function v5517cCanApplyPosDiscount(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        try {
            if (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin()) {
                return true;
            }

            if (method_exists($user, 'isGroupAdmin') && $user->isGroupAdmin()) {
                return true;
            }

            return method_exists($user, 'can') && $user->can('pos.discount.apply');
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function v5517cNormalizeDiscountInput($discountInput): ?array
    {
        if (is_string($discountInput) && trim($discountInput) !== '') {
            $decoded = json_decode($discountInput, true);
            $discountInput = is_array($decoded) ? $decoded : null;
        }

        return is_array($discountInput) ? $discountInput : null;
    }

    protected function v5517cHasDiscountInput($discountInput): bool
    {
        $discountInput = $this->v5517cNormalizeDiscountInput($discountInput);

        if (! is_array($discountInput)) {
            return false;
        }

        return (float) ($discountInput['value'] ?? 0) > 0;
    }


    protected function v5517dAuditDiscountFromSavedOrder($orderId, string $source = 'unknown'): void
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('pos_audit_logs')) {
                return;
            }

            if (! \Illuminate\Support\Facades\Schema::hasTable('pos_orders')) {
                return;
            }

            $order = \Illuminate\Support\Facades\DB::table('pos_orders')
                ->where('id', (int) $orderId)
                ->first();

            if (! $order) {
                return;
            }

            $metadataRaw = $order->metadata ?? null;
            $metadata = [];

            if (is_string($metadataRaw) && trim($metadataRaw) !== '') {
                $decoded = json_decode($metadataRaw, true);
                $metadata = is_array($decoded) ? $decoded : [];
            } elseif (is_array($metadataRaw)) {
                $metadata = $metadataRaw;
            }

            $discount = $metadata['discount'] ?? null;

            if (! is_array($discount)) {
                return;
            }

            $amount = (float) ($discount['amount'] ?? 0);

            if ($amount <= 0) {
                return;
            }

            $exists = \Illuminate\Support\Facades\DB::table('pos_audit_logs')
                ->where('action', 'pos.discount.applied')
                ->where('pos_order_id', (int) $order->id)
                ->where('metadata', 'like', '%"source":"' . $source . '"%')
                ->exists();

            if ($exists) {
                return;
            }

            if (method_exists($this, 'v5515aWritePosAuditLog')) {
                $this->v5515aWritePosAuditLog('pos.discount.applied', [
                    'company_id' => $order->company_id ?? null,
                    'user_id' => auth()->id() ?: ($discount['user_id'] ?? null),
                    'pos_session_id' => $order->pos_session_id ?? null,
                    'pos_order_id' => $order->id ?? null,
                    'entity_type' => 'pos_order',
                    'entity_id' => $order->id ?? null,
                    'description' => 'Descuento aplicado en PDV.',
                    'after_data' => [
                        'discount' => $discount,
                        'order_total' => $order->total ?? null,
                    ],
                    'metadata' => [
                        'source' => $source,
                        'order_number' => $order->number ?? null,
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('No se pudo auditar descuento post-guardado PDV.', [
                'order_id' => $orderId,
                'source' => $source,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function auditPriceListChange(\Illuminate\Http\Request $request)
    {
        try {
            $payload = $request->validate([
                'company_id' => ['nullable'],
                'user_id' => ['nullable'],
                'pos_session_id' => ['nullable'],
                'pos_point_id' => ['nullable'],
                'pos_order_id' => ['nullable'],
                'old_value' => ['nullable'],
                'new_value' => ['nullable'],
                'old_label' => ['nullable', 'string', 'max:255'],
                'new_label' => ['nullable', 'string', 'max:255'],
                'field_name' => ['nullable', 'string', 'max:255'],
                'source' => ['nullable', 'string', 'max:255'],
                'url' => ['nullable', 'string', 'max:2000'],
            ]);

            $oldValue = $payload['old_value'] ?? null;
            $newValue = $payload['new_value'] ?? null;

            if ((string) $oldValue === (string) $newValue) {
                return response()->json([
                    'ok' => true,
                    'skipped' => true,
                    'message' => 'Sin cambio real de lista de precios.',
                ]);
            }

            $auditPayload = [
                'company_id' => isset($payload['company_id']) && $payload['company_id'] !== ''
                    ? (int) $payload['company_id']
                    : null,

                // V5.51.5L - fallback de usuario si auth()->id() no resuelve en esta llamada.
                'user_id' => isset($payload['user_id']) && $payload['user_id'] !== ''
                    ? (int) $payload['user_id']
                    : null,

                'pos_session_id' => isset($payload['pos_session_id']) && $payload['pos_session_id'] !== ''
                    ? (int) $payload['pos_session_id']
                    : null,

                'pos_order_id' => isset($payload['pos_order_id']) && $payload['pos_order_id'] !== ''
                    ? (int) $payload['pos_order_id']
                    : null,

                'entity_type' => 'pos_session',

                'entity_id' => isset($payload['pos_session_id']) && $payload['pos_session_id'] !== ''
                    ? (int) $payload['pos_session_id']
                    : null,

                'description' => 'Cambio de lista de precios en PDV.',

                'before_data' => [
                    'price_list_id' => $oldValue,
                    'price_list_label' => $payload['old_label'] ?? null,
                ],

                'after_data' => [
                    'price_list_id' => $newValue,
                    'price_list_label' => $payload['new_label'] ?? null,
                ],

                'metadata' => [
                    'pos_point_id' => $payload['pos_point_id'] ?? null,
                    'field_name' => $payload['field_name'] ?? null,
                    'source' => $payload['source'] ?? null,
                    'url' => $payload['url'] ?? null,
                ],
            ];

            if (method_exists($this, 'v5515aWritePosAuditLog')) {
                $this->v5515aWritePosAuditLog('pos.price_list.change', $auditPayload);
            } elseif (\Illuminate\Support\Facades\Schema::hasTable('pos_audit_logs')) {
                \Illuminate\Support\Facades\DB::table('pos_audit_logs')->insert([
                    'company_id' => $auditPayload['company_id'] ?? null,
                    'user_id' => auth()->id() ?: ($auditPayload['user_id'] ?? null),
                    'pos_session_id' => $auditPayload['pos_session_id'] ?? null,
                    'pos_order_id' => $auditPayload['pos_order_id'] ?? null,
                    'action' => 'pos.price_list.change',
                    'entity_type' => 'pos_session',
                    'entity_id' => $auditPayload['entity_id'] ?? null,
                    'description' => 'Cambio de lista de precios en PDV.',
                    'before_data' => json_encode($auditPayload['before_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'after_data' => json_encode($auditPayload['after_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'metadata' => json_encode($auditPayload['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 2000),
                    'created_at' => now(),
                ]);
            }

            return response()->json([
                'ok' => true,
                'message' => 'Cambio de lista de precios auditado.',
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('No se pudo auditar cambio de lista de precios PDV.', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'No se pudo auditar el cambio de lista de precios.',
            ], 422);
        }
    }

    public function closeSession(\Illuminate\Http\Request $request, int $session)
{
        // V5.51.5A - Audit intento cierre caja.
        $this->v5515aWritePosAuditLog('pos.session.close.attempt', [
            'pos_session_id' => $session,
            'entity_type' => 'pos_session',
            'entity_id' => $session,
            'description' => 'Intento de cierre de sesión de caja.',
            'metadata' => [
                'route' => optional($request->route())->getName(),
                'method' => $request->method(),
            ],
        ]);


        // V5.51.4B - Permiso backend cierre caja.
        if (! $this->v5514bCanClosePosSession()) {
            return $this->v5514bForbiddenPosResponse($request, 'No tienes permiso para cerrar sesiones de caja.');
        }


    $row = DB::table('pos_sessions')->where('id', $session)->first();

    if (! $row) {
        abort(404);
    }

    $difference = round((float) $request->input('closing_difference', 0), 2);
    $note = trim((string) $request->input('closing_note', ''));

    if (abs($difference) > 0.009 && $note === '') {
        return redirect()
            ->back()
            ->with('error', 'Hay diferencia en el corte. La nota de cierre es obligatoria.');
    }

    $closingAmount = $request->input('closing_amount', null);
    $cashCount = $request->input('cash_count', null);

    $notes = trim((string) ($row->notes ?? ''));

    $closePayload = [
        'closed_by_user_id' => auth()->id(),
        'closing_note' => $note,
        'closing_difference' => $difference,
        'cash_count' => $cashCount,
        'closed_from' => 'pos_close_modal',
    ];

    $notesAppend = '[CIERRE PDV] ' . json_encode($closePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $notes = $notes !== ''
        ? ($notes . "\n" . $notesAppend)
        : $notesAppend;

    $updates = [
        'status' => 'closed',
        'closed_at' => now(),
        'updated_at' => now(),
        'notes' => $notes,
    ];

    if ($closingAmount !== null && is_numeric($closingAmount) && Schema::hasColumn('pos_sessions', 'closing_amount')) {
        $updates['closing_amount'] = round((float) $closingAmount, 2);
    }

    DB::table('pos_sessions')
        ->where('id', $session)
        ->update($updates);

            $this->v5515aWritePosAuditLog('pos.session.close.success', [
            'pos_session_id' => $session,
            'entity_type' => 'pos_session',
            'entity_id' => $session,
            'description' => 'Sesión de caja cerrada.',
            'metadata' => [
                'route' => optional($request->route())->getName(),
                'method' => $request->method(),
            ],
        ]);

return redirect($this->v5485hPosEmployeeSelectorUrl($row))
        ->with('success', 'Sesión cerrada correctamente.');
}




    protected function normalizePosTaxRate($value): float
    {
        if ($value === null || $value === '') {
            return 0.16;
        }

        $rate = (float) $value;

        if ($rate <= 0) {
            return 0.16;
        }

        if ($rate > 1) {
            $rate = $rate / 100;
        }

        return $rate;
    }

    protected function posProductTaxRate(object $row): float
    {
        foreach ([
            'tax_rate',
            'iva_rate',
            'vat_rate',
            'sale_tax_rate',
            'sales_tax_rate',
            'tax_percent',
            'iva_percent',
            'vat_percent',
        ] as $field) {
            if (property_exists($row, $field) && $row->{$field} !== null && $row->{$field} !== '') {
                return $this->normalizePosTaxRate($row->{$field});
            }
        }

        return 0.16;
    }

    protected function posProductPriceWithoutTax(object $row): float
    {
        foreach ([
            'sale_price',
            'sales_price',
            'list_price',
            'price',
            'public_price',
            'unit_price',
        ] as $field) {
            if (property_exists($row, $field) && $row->{$field} !== null && $row->{$field} !== '') {
                return round((float) $row->{$field}, 4);
            }
        }

        return 0.0;
    }

    protected function posProductPriceWithTax(object $row): float
    {
        foreach ([
            'sale_price_with_tax',
            'sales_price_with_tax',
            'price_with_tax',
            'price_including_tax',
            'sale_price_tax_included',
            'tax_included_price',
            'public_price_with_tax',
        ] as $field) {
            if (property_exists($row, $field) && $row->{$field} !== null && $row->{$field} !== '') {
                $gross = (float) $row->{$field};

                if ($gross > 0) {
                    return round($gross, 2);
                }
            }
        }

        $base = $this->posProductPriceWithoutTax($row);
        $rate = $this->posProductTaxRate($row);

        return round($base * (1 + $rate), 2);
    }




    protected function v5496aResolveOrderPriceList(\Illuminate\Http\Request $request, object $sessionRow, ?object $pos = null): array
    {
        $selectedId = $request->input('price_list_id', $request->input('selected_price_list_id', null));

        $selectedId = is_numeric($selectedId) ? (int) $selectedId : 0;

        $allowedIds = [];

        if ($pos) {
            foreach (['available_price_list_ids', 'allowed_price_list_ids', 'price_list_ids'] as $field) {
                if (! isset($pos->{$field}) || $pos->{$field} === null || $pos->{$field} === '') {
                    continue;
                }

                $raw = $pos->{$field};

                if (is_string($raw)) {
                    $decoded = json_decode($raw, true);
                    $raw = is_array($decoded) ? $decoded : preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY);
                }

                if (is_array($raw)) {
                    foreach ($raw as $id) {
                        if (is_numeric($id) && (int) $id > 0) {
                            $allowedIds[] = (int) $id;
                        }
                    }
                }
            }

            foreach (['default_price_list_id', 'price_list_id'] as $field) {
                if (isset($pos->{$field}) && is_numeric($pos->{$field}) && (int) $pos->{$field} > 0) {
                    $allowedIds[] = (int) $pos->{$field};
                }
            }
        }

        $allowedIds = array_values(array_unique(array_filter($allowedIds, fn ($id) => (int) $id > 0)));

        $defaultId = 0;

        if ($pos) {
            foreach (['default_price_list_id', 'price_list_id'] as $field) {
                if (isset($pos->{$field}) && is_numeric($pos->{$field}) && (int) $pos->{$field} > 0) {
                    $defaultId = (int) $pos->{$field};
                    break;
                }
            }
        }

        if ($selectedId <= 0) {
            $selectedId = $defaultId ?: ($allowedIds[0] ?? 0);
        }

        if (! empty($allowedIds) && $selectedId > 0 && ! in_array($selectedId, $allowedIds, true)) {
            $selectedId = $defaultId && in_array($defaultId, $allowedIds, true)
                ? $defaultId
                : ($allowedIds[0] ?? 0);
        }

        $name = '';

        if ($selectedId > 0 && \Illuminate\Support\Facades\Schema::hasTable('sales_price_lists')) {
            $query = \Illuminate\Support\Facades\DB::table('sales_price_lists')->where('id', $selectedId);

            if (! empty($sessionRow->company_id) && \Illuminate\Support\Facades\Schema::hasColumn('sales_price_lists', 'company_id')) {
                $query->where(function ($q) use ($sessionRow) {
                    $q->where('company_id', $sessionRow->company_id)->orWhereNull('company_id');
                });
            }

            $row = $query->first();

            if ($row) {
                foreach (['name', 'display_name', 'code'] as $field) {
                    if (isset($row->{$field}) && trim((string) $row->{$field}) !== '') {
                        $name = trim((string) $row->{$field});
                        break;
                    }
                }
            }
        }

        if ($name === '') {
            $name = trim((string) $request->input('price_list_name', $request->input('selected_price_list_name', '')));
        }

        if ($name === '') {
            $name = $selectedId > 0 ? ('Lista #' . $selectedId) : 'Precio público';
        }

        return [
            'id' => $selectedId > 0 ? $selectedId : null,
            'name' => $name,
            'allowed_ids' => $allowedIds,
        ];
    }

    protected function v5496aPriceListPayloadFromOrder(object $order): array
    {
        $metadata = [];

        if (! empty($order->metadata)) {
            $decoded = json_decode((string) $order->metadata, true);
            $metadata = is_array($decoded) ? $decoded : [];
        }

        $id = $order->price_list_id ?? ($metadata['price_list_id'] ?? ($metadata['selected_price_list_id'] ?? null));
        $name = $order->price_list_name ?? ($metadata['price_list_name'] ?? ($metadata['selected_price_list_name'] ?? ''));

        $id = is_numeric($id) ? (int) $id : null;
        $name = trim((string) $name);

        return [
            'price_list_id' => $id,
            'price_list_name' => $name !== '' ? $name : null,
        ];
    }


    protected function v5504aCanPendingTicket(string $permission): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin()) {
            return true;
        }

        if (method_exists($user, 'isGroupAdmin') && $user->isGroupAdmin()) {
            return true;
        }

        return method_exists($user, 'can') && $user->can($permission);
    }

    protected function v5504aPendingTicketForbiddenJson(string $message)
    {
        return response()->json([
            'ok' => false,
            'message' => $message,
        ], 403);
    }

    public function storeOrder(\Illuminate\Http\Request $request, int $session)
    {
        abort_unless(auth()->check(), 403);

        // V5.50.4A - permiso para crear tickets pendientes.
        if (! $this->v5504aCanPendingTicket('pos.pending_tickets.create')) {
            return $this->v5504aPendingTicketForbiddenJson('No tienes permiso para crear tickets pendientes.');
        }

        $sessionRow = DB::table('pos_sessions')->where('id', $session)->first();

        abort_if(! $sessionRow, 404, 'Sesión PDV no encontrada.');

        if (($sessionRow->status ?? null) !== 'open') {
            return response()->json([
                'ok' => false,
                'message' => 'La sesión de PDV no está abierta.',
            ], 422);
        }

        $items = collect($request->input('items', []))
            ->filter(fn ($item) => is_array($item))
            ->values();

        if ($items->isEmpty()) {
            return response()->json([
                'ok' => false,
                'message' => 'El carrito está vacío.',
            ], 422);
        }

        $pos = DB::table('pos_points')->where('id', $sessionRow->pos_point_id)->first();
        $v5496aPriceList = $this->v5496aResolveOrderPriceList($request, $sessionRow, $pos);
$companyId = (int) ($sessionRow->company_id ?? $pos->company_id ?? 0);
        $posPointId = (int) ($sessionRow->pos_point_id ?? 0);
        $employeeId = null;

        if (Schema::hasColumn('pos_sessions', 'employee_id') && ! empty($sessionRow->employee_id)) {
            $employeeId = (int) $sessionRow->employee_id;
        }

        $customerId = $request->input('customer_id');

        if (! is_numeric($customerId) || (int) $customerId <= 0) {
            $customerId = null;
        } else {
            $customerId = (int) $customerId;

            if (\Illuminate\Support\Facades\Schema::hasTable('contacts')) {
                $customerExistsQuery = \Illuminate\Support\Facades\DB::table('contacts')->where('id', $customerId);

                if (! empty($companyId) && \Illuminate\Support\Facades\Schema::hasColumn('contacts', 'company_id')) {
                    $customerExistsQuery->where('company_id', $companyId);
                }

                if (! $customerExistsQuery->exists()) {
                    $customerId = null;
                }
            }
        }

        $normalizedItems = [];
        $subtotal = 0.0;
        $taxTotal = 0.0;
        $total = 0.0;

        foreach ($items as $item) {
            $productId = isset($item['product_id']) && is_numeric($item['product_id'])
                ? (int) $item['product_id']
                : null;

            $productVariantId = null;
            $stockSerialNumberId = null;
            $stockLotId = null;

            foreach (['product_variant_id', 'variant_id'] as $variantKey) {
                if (isset($item[$variantKey]) && is_numeric($item[$variantKey]) && (int) $item[$variantKey] > 0) {
                    $productVariantId = (int) $item[$variantKey];
                    break;
                }
            }

            foreach (['stock_serial_number_id', 'serial_number_id'] as $serialKey) {
                if (isset($item[$serialKey]) && is_numeric($item[$serialKey]) && (int) $item[$serialKey] > 0) {
                    $stockSerialNumberId = (int) $item[$serialKey];
                    break;
                }
            }

            foreach (['stock_lot_id', 'lot_id'] as $lotKey) {
                if (isset($item[$lotKey]) && is_numeric($item[$lotKey]) && (int) $item[$lotKey] > 0) {
                    $stockLotId = (int) $item[$lotKey];
                    break;
                }
            }

            if (! $stockLotId && isset($item['metadata']) && is_array($item['metadata'])) {
                foreach (['stock_lot_id', 'lot_id'] as $lotKey) {
                    if (isset($item['metadata'][$lotKey]) && is_numeric($item['metadata'][$lotKey]) && (int) $item['metadata'][$lotKey] > 0) {
                        $stockLotId = (int) $item['metadata'][$lotKey];
                        break;
                    }
                }
            }

            if (! $stockLotId && isset($item['raw']) && is_array($item['raw'])) {
                foreach (['stock_lot_id', 'lot_id'] as $lotKey) {
                    if (isset($item['raw'][$lotKey]) && is_numeric($item['raw'][$lotKey]) && (int) $item['raw'][$lotKey] > 0) {
                        $stockLotId = (int) $item['raw'][$lotKey];
                        break;
                    }
                }
            }

            if ($stockLotId && Schema::hasTable('stock_lots')) {
                $lot = DB::table('stock_lots')
                    ->where('id', $stockLotId)
                    ->first();

                if ($lot) {
                    $productId = (int) ($lot->product_id ?? $productId);
                    $productVariantId = ! empty($lot->product_variant_id) ? (int) $lot->product_variant_id : $productVariantId;
                }
            }

            if ($stockSerialNumberId && Schema::hasTable('stock_serial_numbers')) {
                $serial = DB::table('stock_serial_numbers')
                    ->where('id', $stockSerialNumberId)
                    ->first();

                if ($serial) {
                    $productId = (int) ($serial->product_id ?? $productId);
                    $productVariantId = ! empty($serial->product_variant_id) ? (int) $serial->product_variant_id : $productVariantId;
                }
            }

            if (
                ! $productVariantId
                && $productId
                && Schema::hasTable('products')
                && Schema::hasColumn('products', 'parent_product_id')
            ) {
                $product = DB::table('products')
                    ->where('id', $productId)
                    ->first();

                if ($product && ! empty($product->parent_product_id)) {
                    $productVariantId = $productId;
                    $productId = (int) $product->parent_product_id;
                }
            }

            $name = trim((string) ($item['name'] ?? 'Producto'));
            $reference = trim((string) ($item['reference'] ?? ''));

            $qty = (float) ($item['qty'] ?? 0);
            $unitPrice = (float) ($item['price'] ?? 0);

            // BEXIA_V5820E2A1_STORE_ORDER_BLOCK_ZERO_PRICE
            if ($unitPrice <= 0) {
                $productLabel = (string) ($item['name'] ?? $item['product_name'] ?? 'Producto');
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'items' => ["No se puede vender {$productLabel} porque no tiene precio valido en el POS."],
                ]);
            }
            $taxRate = (float) ($item['tax_rate'] ?? 0.16);

            if ($taxRate > 1) {
                $taxRate = $taxRate / 100;
            }

            if ($taxRate < 0) {
                $taxRate = 0;
            }

            if ($qty <= 0 || $unitPrice < 0) {
                continue;
            }

            $lineTotal = round($qty * $unitPrice, 4);
            $lineSubtotal = $taxRate > 0
                ? round($lineTotal / (1 + $taxRate), 4)
                : $lineTotal;
            $lineTax = round($lineTotal - $lineSubtotal, 4);

            $subtotal += $lineSubtotal;
            $taxTotal += $lineTax;
            $total += $lineTotal;

            $normalizedItems[] = [
                'product_id' => $productId,
                'product_variant_id' => Schema::hasColumn('pos_order_lines', 'product_variant_id') ? $productVariantId : null,
                'stock_serial_number_id' => Schema::hasColumn('pos_order_lines', 'stock_serial_number_id') ? $stockSerialNumberId : null,
                'stock_lot_id' => Schema::hasColumn('pos_order_lines', 'stock_lot_id') ? $stockLotId : null,
                'lot_tracking_metadata' => Schema::hasColumn('pos_order_lines', 'lot_tracking_metadata') && $stockLotId
                    ? json_encode([
                        'stock_lot_id' => $stockLotId,
                        'source_type' => 'pos_order',
                        'source_line_type' => 'pos_order_line',
                        'selected_from' => 'pos_lot_selector',
                        'updated_at' => now()->toDateTimeString(),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null,
                'product_name' => $name ?: 'Producto',
                'product_reference' => $reference ?: null,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
                'subtotal' => $lineSubtotal,
                'tax_total' => $lineTax,
                'total' => $lineTotal,
                'metadata' => json_encode([
                    'source' => 'pos_frontend',
                    'raw' => $item,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (empty($normalizedItems)) {
            return response()->json([
                'ok' => false,
                'message' => 'No hay productos válidos en el carrito.',
            ], 422);
        }

        $subtotal = round($subtotal, 4);
        $taxTotal = round($taxTotal, 4);
        $total = round($total, 4);

        $orderId = null;
        $number = null;
        $finalTotal = $total;

        DB::transaction(function () use (
            &$orderId,
            &$number,
            &$finalTotal,
            $companyId,
            $posPointId,
            $session,
            $employeeId,
            $customerId,
            $subtotal,
            $taxTotal,
            $total,
            $normalizedItems,
            $request,
            $v5496aPriceList
        ) {
            $posRow = DB::table('pos_points')->where('id', $posPointId)->first();

            $rawPrefix = '';

            if ($posRow) {
                foreach (['code', 'pos_code', 'ticket_code', 'receipt_prefix', 'prefix'] as $field) {
                    if (isset($posRow->{$field}) && trim((string) $posRow->{$field}) !== '') {
                        $rawPrefix = trim((string) $posRow->{$field});
                        break;
                    }
                }
            }

            if ($rawPrefix === '') {
                $rawPrefix = 'PDV';
            }

            $cleanPrefix = strtoupper(preg_replace('/[^A-Z0-9]+/', '', $rawPrefix));
            $cleanPrefix = $cleanPrefix !== '' ? $cleanPrefix : 'PDV';

            $prefix = $cleanPrefix . '-' . now()->format('Ymd') . '-';

            $lastNumber = DB::table('pos_orders')
                ->where('number', 'like', $prefix . '%')
                ->orderByDesc('number')
                ->value('number');

            $next = 1;

            if ($lastNumber && preg_match('/-(\d+)$/', (string) $lastNumber, $matches)) {
                $next = ((int) $matches[1]) + 1;
            }

            do {
                $number = $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
                $exists = DB::table('pos_orders')->where('number', $number)->exists();
                $next++;
            } while ($exists);

            $orderNote = trim((string) $request->input('order_note', ''));

            $noteMetadata = [
                'source' => 'pos_frontend',
                'payment_label' => $request->input('payment_label'),
                'price_list_id' => $v5496aPriceList['id'] ?? null,
                'price_list_name' => $v5496aPriceList['name'] ?? null,
            ];

            if ($orderNote !== '') {
                $noteMetadata['order_note'] = $orderNote;
            }

            $orderSubtotal = $subtotal;
            $orderTaxTotal = $taxTotal;
            $orderTotal = $total;
            $discountMetadata = null;

            $discountInput = $request->input('discount');

            // V5.51.7C - Backend permiso descuento PDV en creación.
            if ($this->v5517cHasDiscountInput($discountInput) && ! $this->v5517cCanApplyPosDiscount()) {
                try {
                    if (method_exists($this, 'v5515aWritePosAuditLog')) {
                        $this->v5515aWritePosAuditLog('pos.discount.blocked', [
                            'company_id' => isset($pos) && is_object($pos) ? ($pos->company_id ?? null) : null,
                            'pos_session_id' => isset($session) && is_object($session) ? ($session->id ?? null) : (is_numeric($session ?? null) ? (int) $session : null),
                            'entity_type' => 'pos_order',
                            'description' => 'Intento bloqueado de aplicar descuento en PDV.',
                            'after_data' => [
                                'discount' => $this->v5517cNormalizeDiscountInput($discountInput),
                            ],
                            'metadata' => [
                                'source' => 'v5517c_store_order_discount_guard',
                                'route' => optional($request->route())->getName(),
                                'url' => $request->fullUrl(),
                            ],
                        ]);
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('No se pudo auditar descuento bloqueado PDV.', [
                        'error' => $e->getMessage(),
                    ]);
                }

                return response()->json([
                    'ok' => false,
                    'message' => 'No tienes permiso para aplicar descuentos.',
                ], 403);
            }


            if (is_string($discountInput) && trim($discountInput) !== '') {
                $decodedDiscount = json_decode($discountInput, true);
                $discountInput = is_array($decodedDiscount) ? $decodedDiscount : null;
            }

            if (is_array($discountInput)) {
                $discountType = (string) ($discountInput['type'] ?? 'amount');
                $discountType = $discountType === 'percent' ? 'percent' : 'amount';

                $discountValue = (float) ($discountInput['value'] ?? 0);
                $discountAmount = 0.0;

                if ($discountValue > 0) {
                    if ($discountType === 'percent') {
                        $discountValue = min($discountValue, 100);
                        $discountAmount = round($total * ($discountValue / 100), 4);
                    } else {
                        $discountAmount = round($discountValue, 4);
                    }

                    $discountAmount = max(0, min($discountAmount, $total));

                    if ($discountAmount > 0) {
                        $factor = $total > 0 ? max(0, ($total - $discountAmount) / $total) : 1;

                        $orderSubtotal = round($subtotal * $factor, 4);
                        $orderTaxTotal = round($taxTotal * $factor, 4);
                        $orderTotal = round($total - $discountAmount, 4);

                        $discountMetadata = [
                            'type' => $discountType,
                            'value' => $discountValue,
                            'amount' => $discountAmount,
                            'user_id' => $discountInput['user_id'] ?? auth()->id(),
                            'user_name' => $discountInput['user_name'] ?? (auth()->user()->name ?? auth()->user()->email ?? 'Usuario'),
                            'applied_at' => $discountInput['applied_at'] ?? now()->toISOString(),
                        ];

                        $noteMetadata['discount'] = $discountMetadata;


                    }
                }
            }

            $finalTotal = $orderTotal;

            $orderId = DB::table('pos_orders')->insertGetId([
                'company_id' => $companyId ?: null,
                'pos_point_id' => $posPointId ?: null,
                'pos_session_id' => $session,
                'employee_id' => $employeeId,
                'customer_id' => $customerId,
                'price_list_id' => \Illuminate\Support\Facades\Schema::hasColumn('pos_orders', 'price_list_id') ? ($v5496aPriceList['id'] ?? null) : null,
                'price_list_name' => \Illuminate\Support\Facades\Schema::hasColumn('pos_orders', 'price_list_name') ? ($v5496aPriceList['name'] ?? null) : null,
                'number' => $number,
                'status' => 'pending_payment',
                'subtotal' => $orderSubtotal,
                'tax_total' => $orderTaxTotal,
                'total' => $orderTotal,
                'currency_code' => 'MXN',
                'metadata' => json_encode($noteMetadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ordered_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // V5.51.7D - Auditar descuento después de crear ticket.
            $this->v5517dAuditDiscountFromSavedOrder($orderId, 'v5517d_discount_after_insert_order');


            foreach ($normalizedItems as $line) {
                $line['pos_order_id'] = $orderId;
                DB::table('pos_order_lines')->insert($line);
            }

            app(\App\Support\PosStockReservationService::class)->reserveOrder($orderId, auth()->id());

            $paymentLabel = trim((string) $request->input('payment_label', ''));

            if ($paymentLabel !== '') {
                DB::table('pos_order_payments')->insert([
                    'pos_order_id' => $orderId,
                    'payment_form_id' => null,
                    'payment_label' => $paymentLabel,
                    'amount' => $orderTotal,
                    'status' => 'pending',
                    'metadata' => json_encode([
                        'source' => 'pos_frontend',
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return response()->json([
            'ok' => true,
            'message' => 'Ticket creado correctamente.',
            'order_id' => $orderId,
            'number' => $this->v5375cNextOrderNumberForPos($pos),
            'status' => 'pending_payment',
            'total' => $finalTotal,
        ]);
    }

    public function showOrder(\Illuminate\Http\Request $request, int $order)
    {
        // V5.50.4A - permiso para ver detalle de tickets pendientes.
        if (! $this->v5504aCanPendingTicket('pos.pending_tickets.view')) {
            return $this->v5504aPendingTicketForbiddenJson('No tienes permiso para ver tickets pendientes.');
        }

        if (! auth()->check()) {
            return response()->json([
                'ok' => false,
                'message' => 'Tu sesión expiró. Vuelve a iniciar sesión.',
            ], 401);
        }

        $orderRow = \Illuminate\Support\Facades\DB::table('pos_orders')
            ->where('id', $order)
            ->first();

        if (! $orderRow) {
            return response()->json([
                'ok' => false,
                'message' => 'No se encontró el ticket.',
            ], 404);
        }

        if ((string) ($orderRow->status ?? '') !== 'pending_payment') {
            return response()->json([
                'ok' => false,
                'message' => 'Este ticket ya no está pendiente de cobro.',
            ], 422);
        }

        $metadata = [];

        if (! empty($orderRow->metadata)) {
            $decodedMetadata = json_decode((string) $orderRow->metadata, true);

            if (is_array($decodedMetadata)) {
                $metadata = $decodedMetadata;
            }
        }

        $customer = null;

        if (! empty($orderRow->customer_id) && \Illuminate\Support\Facades\Schema::hasTable('contacts')) {
            $contact = \Illuminate\Support\Facades\DB::table('contacts')
                ->where('id', (int) $orderRow->customer_id)
                ->first();

            if ($contact) {
                $name = '';

                foreach (['name', 'commercial_name', 'business_name', 'display_name', 'legal_name'] as $field) {
                    if (isset($contact->{$field}) && trim((string) $contact->{$field}) !== '') {
                        $name = trim((string) $contact->{$field});
                        break;
                    }
                }

                $customer = [
                    'id' => (int) $contact->id,
                    'name' => $name !== '' ? $name : ('Cliente #' . $contact->id),
                    'rfc' => (string) ($contact->rfc ?? ''),
                    'email' => (string) ($contact->email ?? ''),
                    'phone' => (string) ($contact->phone ?? ''),
                ];
            }
        }

        $sellerName = 'Sin vendedor asignado';

        if (! empty($orderRow->employee_id) && \Illuminate\Support\Facades\Schema::hasTable('employees')) {
            $employee = \Illuminate\Support\Facades\DB::table('employees')
                ->where('id', (int) $orderRow->employee_id)
                ->first();

            if ($employee && isset($employee->name) && trim((string) $employee->name) !== '') {
                $sellerName = trim((string) $employee->name);
            }
        }

        $lines = \Illuminate\Support\Facades\DB::table('pos_order_lines')
            ->where('pos_order_id', $orderRow->id)
            ->orderBy('id')
            ->get()
            ->map(function ($line) {
                return [
                    'product_id' => $line->product_id ? (int) $line->product_id : null,
                    'product_variant_id' => ! empty($line->product_variant_id) ? (int) $line->product_variant_id : null,
                    'stock_serial_number_id' => ! empty($line->stock_serial_number_id) ? (int) $line->stock_serial_number_id : null,
                    'stock_lot_id' => ! empty($line->stock_lot_id) ? (int) $line->stock_lot_id : null,
                    'lot_number' => ! empty($line->stock_lot_id) && \Illuminate\Support\Facades\Schema::hasTable('stock_lots')
                        ? (string) (\Illuminate\Support\Facades\DB::table('stock_lots')->where('id', (int) $line->stock_lot_id)->value('lot_number') ?? '')
                        : '',
                    'lot_locked_from_pending' => ! empty($line->stock_lot_id),
                    'serial_number' => ! empty($line->stock_serial_number_id) && \Illuminate\Support\Facades\Schema::hasTable('stock_serial_numbers')
                        ? (string) (\Illuminate\Support\Facades\DB::table('stock_serial_numbers')->where('id', (int) $line->stock_serial_number_id)->value('serial_number') ?? '')
                        : '',
                    'serial_locked_from_pending' => ! empty($line->stock_serial_number_id),
                    // BEXIA_V5544D_PENDING_SERIAL_LOCK_PAYLOAD
                    'name' => (string) ($line->product_name ?? 'Producto'),
                    'reference' => (string) ($line->product_reference ?? ''),
                    'qty' => (float) ($line->quantity ?? 0),
                    'price' => (float) ($line->unit_price ?? 0),
                    'tax_rate' => (float) ($line->tax_rate ?? 0),
                    'line_total' => (float) ($line->total ?? 0),
                    'total' => (float) ($line->total ?? 0),
                ];
            })
            ->values();

        return response()->json([
            'ok' => true,
            'order' => [
                'id' => (int) $orderRow->id,
                'number' => (string) $orderRow->number,
                'status' => (string) $orderRow->status,
                'status_label' => 'Pendiente de cobro',
                'total' => (float) $orderRow->total,
                'seller_name' => $sellerName,
                'customer_id' => $customer['id'] ?? null,
                'customer_name' => $customer['name'] ?? null,
                'customer' => $customer,
                'metadata' => $metadata,
                'order_note' => $metadata['order_note'] ?? '',
                'discount' => $metadata['discount'] ?? null,
                'price_list_id' => $this->v5496aPriceListPayloadFromOrder($orderRow)['price_list_id'] ?? null,
                'price_list_name' => $this->v5496aPriceListPayloadFromOrder($orderRow)['price_list_name'] ?? null,
                'items' => $lines,
            ],
        ]);
    }








    public function paymentMethods(\Illuminate\Http\Request $request, int $session)
    {
        if (! auth()->check()) {
            return response()->json([
                'ok' => false,
                'message' => 'Tu sesión expiró. Vuelve a iniciar sesión para continuar.',
            ], 401);
        }

        $sessionRow = DB::table('pos_sessions')->where('id', $session)->first();

        if (! $sessionRow) {
            return response()->json([
                'ok' => false,
                'message' => 'No se encontró la sesión del PDV.',
            ], 404);
        }

        $pos = DB::table('pos_points')->where('id', $sessionRow->pos_point_id)->first();

        if (! $pos) {
            return response()->json([
                'ok' => false,
                'message' => 'No se encontró el punto de venta.',
            ], 404);
        }

        $rawIds = null;

        foreach (['payment_method_ids', 'payment_form_ids', 'allowed_payment_form_ids'] as $field) {
            if (isset($pos->{$field}) && ! empty($pos->{$field})) {
                $rawIds = $pos->{$field};
                break;
            }
        }

        if (is_string($rawIds) && trim($rawIds) !== '') {
            $decoded = json_decode($rawIds, true);
            $rawIds = is_array($decoded) ? $decoded : preg_split('/\s*,\s*/', $rawIds, -1, PREG_SPLIT_NO_EMPTY);
        }

        $ids = collect(is_array($rawIds) ? $rawIds : [])
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $methods = collect();

        if (Schema::hasTable('payment_forms') && $ids->isNotEmpty()) {
            $query = DB::table('payment_forms')->whereIn('id', $ids->all());

            if (Schema::hasColumn('payment_forms', 'is_active')) {
                $query->where('is_active', true);
            } elseif (Schema::hasColumn('payment_forms', 'active')) {
                $query->where('active', true);
            }

            $methods = $query
                ->orderBy('id')
                ->get()
                ->map(function ($row) {
                    $name = trim((string) ($row->name ?? ''));
                    $description = trim((string) ($row->description ?? $row->label ?? ''));

                    $label = $name !== '' ? $name : $description;

                    // Quitar código inicial: "01 - Efectivo - Pago en efectivo" => "Efectivo"
                    $label = preg_replace('/^\s*\d+\s*[-–]\s*/', '', $label);

                    // Si viene duplicado con descripción, dejar solo el primer texto útil.
                    if (str_contains($label, ' - ')) {
                        $parts = array_values(array_filter(array_map('trim', explode(' - ', $label))));
                        $label = $parts[0] ?? $label;
                    }

                    if ($label === '') {
                        $label = 'Método de pago';
                    }

                    return [
                        'id' => (int) ($row->id ?? 0),
                        'label' => $label,
                    ];
                })
                ->values();
        }

        return response()->json([
            'ok' => true,
            'methods' => $methods,
        ]);
    }


    public function pendingOrders(\Illuminate\Http\Request $request, int $session)
{
        // V5.50.4A - permiso pendingOrders.
        if (! $this->v5504aCanPendingTicket('pos.pending_tickets.view')) {
            return $this->v5504aPendingTicketForbiddenJson('No tienes permiso para ver tickets pendientes.');
        }

    abort_unless(auth()->check(), 403);

    $sessionRow = \Illuminate\Support\Facades\DB::table('pos_sessions')
        ->where('id', $session)
        ->first();

    abort_if(! $sessionRow, 404, 'Sesión PDV no encontrada.');

    $pos = $this->posPoint((int) $sessionRow->pos_point_id);
    $this->authorizePos($pos);

    $orders = $this->v5483PendingOrdersQuery($sessionRow, $pos)
        ->orderByDesc('o.id')
        ->limit(80)
        ->get()
        ->map(fn ($row) => $this->v5483PendingOrderPayload($row, $sessionRow))
        ->values();

    return response()->json([
        'ok' => true,
        'orders' => $orders,
    ]);
}





    protected function buildPendingOrderPayload(object $sessionRow, object $order): array
    {
        $sellerName = 'Sin vendedor asignado';

        if (
            Schema::hasTable('employees')
            && Schema::hasColumn('pos_orders', 'employee_id')
            && ! empty($order->employee_id)
        ) {
            $employeeNameColumn = null;

            foreach (['name', 'full_name', 'display_name', 'nombre', 'employee_name'] as $candidate) {
                if (Schema::hasColumn('employees', $candidate)) {
                    $employeeNameColumn = $candidate;
                    break;
                }
            }

            if ($employeeNameColumn) {
                $employee = DB::table('employees')->where('id', $order->employee_id)->first();

                if ($employee && ! empty($employee->{$employeeNameColumn})) {
                    $sellerName = (string) $employee->{$employeeNameColumn};
                }
            }
        }

        $lines = collect();

        if (Schema::hasTable('pos_order_lines')) {
            $lines = DB::table('pos_order_lines')
                ->where('pos_order_id', $order->id)
                ->orderBy('id')
                ->get()
                ->map(function ($line) {
                    return [
                        'id' => (int) $line->id,
                        'product_id' => ! empty($line->product_id) ? (int) $line->product_id : null,
                        'product_variant_id' => ! empty($line->product_variant_id) ? (int) $line->product_variant_id : null,
                        'stock_serial_number_id' => ! empty($line->stock_serial_number_id) ? (int) $line->stock_serial_number_id : null,
                        'stock_lot_id' => ! empty($line->stock_lot_id) ? (int) $line->stock_lot_id : null,
                        'lot_number' => ! empty($line->stock_lot_id) && Schema::hasTable('stock_lots')
                            ? (string) (DB::table('stock_lots')->where('id', (int) $line->stock_lot_id)->value('lot_number') ?? '')
                            : '',
                        'lot_locked_from_pending' => ! empty($line->stock_lot_id),
                        'serial_number' => ! empty($line->stock_serial_number_id) && Schema::hasTable('stock_serial_numbers')
                            ? (string) (DB::table('stock_serial_numbers')->where('id', (int) $line->stock_serial_number_id)->value('serial_number') ?? '')
                            : '',
                        'serial_locked_from_pending' => ! empty($line->stock_serial_number_id),
                    // BEXIA_V5544D_PENDING_SERIAL_LOCK_PAYLOAD
                    'name' => (string) ($line->product_name ?? 'Producto'),
                        'reference' => (string) ($line->product_reference ?? ''),
                        'qty' => (float) ($line->quantity ?? 0),
                        'price' => (float) ($line->unit_price ?? 0),
                        'tax_rate' => (float) ($line->tax_rate ?? 0.16),
                        'subtotal' => (float) ($line->subtotal ?? 0),
                        'tax_total' => (float) ($line->tax_total ?? 0),
                        'total' => (float) ($line->total ?? 0),
                    ];
                })
                ->values();
        }

        return [
            'id' => (int) $order->id,
            'number' => (string) $order->number,
            'status' => (string) $order->status,
            'status_label' => 'Pendiente de cobro',
            'total' => (float) $order->total,
            'price_list_id' => $this->v5496aPriceListPayloadFromOrder($order)['price_list_id'] ?? null,
            'price_list_name' => $this->v5496aPriceListPayloadFromOrder($order)['price_list_name'] ?? null,
                'customer_id' => $order->customer_id ?? null,
                'customer' => $this->v5395CustomerPayloadFromOrder($order),
                'customer_id' => $order->customer_id ?? null,
                'customer' => $this->v5395CustomerPayloadFromOrder($order),
                'customer' => $this->v5393CustomerPayloadFromOrder($order),
                'customer_id' => $order->customer_id ?? null,
            'subtotal' => (float) ($order->subtotal ?? 0),
            'tax_total' => (float) ($order->tax_total ?? 0),
            'seller_name' => $sellerName,
            'employee_id' => ! empty($order->employee_id) ? (int) $order->employee_id : null,
            'created_at' => (string) ($order->created_at ?? ''),
            'items' => $lines,
        ];
    }


    public function pendingOrderDetail(\Illuminate\Http\Request $request, int $session, int $order)
{
        // V5.50.4A - permiso pendingOrderDetail.
        if (! $this->v5504aCanPendingTicket('pos.pending_tickets.view')) {
            return $this->v5504aPendingTicketForbiddenJson('No tienes permiso para ver tickets pendientes.');
        }

    abort_unless(auth()->check(), 403);

    $sessionRow = \Illuminate\Support\Facades\DB::table('pos_sessions')
        ->where('id', $session)
        ->first();

    abort_if(! $sessionRow, 404, 'Sesión PDV no encontrada.');

    $pos = $this->posPoint((int) $sessionRow->pos_point_id);
    $this->authorizePos($pos);

    $orderRow = $this->v5483PendingOrdersQuery($sessionRow, $pos)
        ->where('o.id', $order)
        ->first();

    if (! $orderRow) {
        return response()->json([
            'ok' => false,
            'message' => 'No se encontró el ticket pendiente en este PDV.',
        ], 404);
    }

    return $this->showOrder($request, $order);
}




    public function pendingOrderSearch(\Illuminate\Http\Request $request, int $session)
{
        // V5.50.4A - permiso pendingOrderSearch.
        if (! $this->v5504aCanPendingTicket('pos.pending_tickets.view')) {
            return $this->v5504aPendingTicketForbiddenJson('No tienes permiso para buscar tickets pendientes.');
        }

    abort_unless(auth()->check(), 403);

    $sessionRow = \Illuminate\Support\Facades\DB::table('pos_sessions')
        ->where('id', $session)
        ->first();

    abort_if(! $sessionRow, 404, 'Sesión PDV no encontrada.');

    $pos = $this->posPoint((int) $sessionRow->pos_point_id);
    $this->authorizePos($pos);

    $term = trim((string) $request->query('q', $request->query('term', $request->query('ticket', ''))));

    if ($term === '') {
        return response()->json([
            'ok' => false,
            'message' => 'Escribe o escanea el folio del ticket.',
        ], 422);
    }

    $cleanTerm = preg_replace('/\s+/', '', $term);

    $query = $this->v5483PendingOrdersQuery($sessionRow, $pos);

    $query->where(function ($q) use ($term, $cleanTerm) {
        $q->where('o.number', 'ilike', '%' . $term . '%');

        if ($cleanTerm !== $term) {
            $q->orWhere('o.number', 'ilike', '%' . $cleanTerm . '%');
        }

        if (is_numeric($term)) {
            $q->orWhere('o.id', (int) $term);
        }
    });

    $orders = $query
        ->orderByDesc('o.id')
        ->limit(20)
        ->get()
        ->map(fn ($row) => $this->v5483PendingOrderPayload($row, $sessionRow))
        ->values();

    return response()->json([
        'ok' => true,
        'orders' => $orders,
        'order' => $orders->first(),
    ]);
}





    protected function v5364TicketPrefixFromPos(object $pos): string
    {
        $raw = (string) (
            $pos->code
            ?? $pos->pos_code
            ?? $pos->ticket_code
            ?? $pos->receipt_prefix
            ?? $pos->prefix
            ?? ''
        );

        $raw = trim($raw);

        if ($raw === '') {
            $raw = 'PDV';
        }

        $prefix = strtoupper($raw);
        $prefix = preg_replace('/[^A-Z0-9]+/', '', $prefix);

        return $prefix !== '' ? $prefix : 'PDV';
    }

    protected function v5364NextPendingTicketNumber(object $pos): string
    {
        $prefix = $this->v5364TicketPrefixFromPos($pos);
        $date = now()->format('Ymd');
        $base = $prefix . '-' . $date . '-';

        $last = DB::table('pos_orders')
            ->where('number', 'like', $base . '%')
            ->orderByDesc('number')
            ->value('number');

        $next = 1;

        if ($last && preg_match('/-(\d+)$/', (string) $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return $base . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }



    protected function v5373PdvPrefixFromPos(object $pos): string
    {
        $raw = '';

        foreach (['code', 'pos_code', 'ticket_code', 'receipt_prefix', 'prefix'] as $field) {
            if (isset($pos->{$field}) && trim((string) $pos->{$field}) !== '') {
                $raw = trim((string) $pos->{$field});
                break;
            }
        }

        if ($raw === '') {
            $raw = 'PDV';
        }

        $prefix = strtoupper($raw);
        $prefix = preg_replace('/[^A-Z0-9]+/', '', $prefix);

        return $prefix !== '' ? $prefix : 'PDV';
    }

    protected function v5373NextOrderNumberForPos(object $pos): string
    {
        $prefix = $this->v5373PdvPrefixFromPos($pos);
        $date = now()->format('Ymd');
        $base = $prefix . '-' . $date . '-';

        $last = \Illuminate\Support\Facades\DB::table('pos_orders')
            ->where('number', 'like', $base . '%')
            ->orderByDesc('number')
            ->value('number');

        $next = 1;

        if ($last && preg_match('/-(\d+)$/', (string) $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return $base . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }



    protected function v5375PdvPrefixFromPos(object $pos): string
    {
        $raw = '';

        foreach (['code', 'pos_code', 'ticket_code', 'receipt_prefix', 'prefix'] as $field) {
            if (isset($pos->{$field}) && trim((string) $pos->{$field}) !== '') {
                $raw = trim((string) $pos->{$field});
                break;
            }
        }

        if ($raw === '') {
            $raw = 'PDV';
        }

        $prefix = strtoupper($raw);
        $prefix = preg_replace('/[^A-Z0-9]+/', '', $prefix);

        return $prefix !== '' ? $prefix : 'PDV';
    }

    protected function v5375NextOrderNumberForPos(object $pos): string
    {
        $prefix = $this->v5375PdvPrefixFromPos($pos);
        $date = now()->format('Ymd');
        $base = $prefix . '-' . $date . '-';

        $last = \Illuminate\Support\Facades\DB::table('pos_orders')
            ->where('number', 'like', $base . '%')
            ->orderByDesc('number')
            ->value('number');

        $next = 1;

        if ($last && preg_match('/-(\d+)$/', (string) $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return $base . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }



    protected function v5375bPdvPrefixFromPos(object $pos): string
    {
        $raw = '';

        foreach (['code', 'pos_code', 'ticket_code', 'receipt_prefix', 'prefix'] as $field) {
            if (isset($pos->{$field}) && trim((string) $pos->{$field}) !== '') {
                $raw = trim((string) $pos->{$field});
                break;
            }
        }

        if ($raw === '') {
            $raw = 'PDV';
        }

        $prefix = strtoupper($raw);
        $prefix = preg_replace('/[^A-Z0-9]+/', '', $prefix);

        return $prefix !== '' ? $prefix : 'PDV';
    }

    protected function v5375bNextOrderNumberForPos(object $pos): string
    {
        $prefix = $this->v5375bPdvPrefixFromPos($pos);
        $date = now()->format('Ymd');
        $base = $prefix . '-' . $date . '-';

        $last = \Illuminate\Support\Facades\DB::table('pos_orders')
            ->where('number', 'like', $base . '%')
            ->orderByDesc('number')
            ->value('number');

        $next = 1;

        if ($last && preg_match('/-(\d+)$/', (string) $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return $base . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }



    protected function v5375cPdvPrefixFromPos(object $pos): string
    {
        $raw = '';

        foreach (['code', 'pos_code', 'ticket_code', 'receipt_prefix', 'prefix'] as $field) {
            if (isset($pos->{$field}) && trim((string) $pos->{$field}) !== '') {
                $raw = trim((string) $pos->{$field});
                break;
            }
        }

        if ($raw === '') {
            $raw = 'PDV';
        }

        $prefix = strtoupper($raw);
        $prefix = preg_replace('/[^A-Z0-9]+/', '', $prefix);

        return $prefix !== '' ? $prefix : 'PDV';
    }

    protected function v5375cNextOrderNumberForPos(object $pos): string
    {
        $prefix = $this->v5375cPdvPrefixFromPos($pos);
        $date = now()->format('Ymd');
        $base = $prefix . '-' . $date . '-';

        $last = \Illuminate\Support\Facades\DB::table('pos_orders')
            ->where('number', 'like', $base . '%')
            ->orderByDesc('number')
            ->value('number');

        $next = 1;

        if ($last && preg_match('/-(\d+)$/', (string) $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return $base . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }




    protected function receiptSellerDisplayName(?object $posRow, object $orderRow, string $employeeName): string
    {
        /*
         * BEXIA_V5527D5_RECEIPT_SELLER_DISPLAY_MODE
         * Solo cambia el texto del vendedor en ticket impreso.
         * No afecta pantalla PDV, permisos, sesión, tickets pendientes ni cierre de caja.
         */
        $mode = (string) ($posRow->receipt_seller_display_mode ?? 'staff_name');

        $employeeName = trim($employeeName);
        $posCode = trim((string) ($posRow->code ?? ''));
        $posName = trim((string) ($posRow->name ?? ''));
        $sessionNumber = '';

        if (! empty($orderRow->pos_session_id)) {
            $sessionNumber = (string) \Illuminate\Support\Facades\DB::table('pos_sessions')
                ->where('id', (int) $orderRow->pos_session_id)
                ->value('number');
        }

        $sessionNumber = trim($sessionNumber);

        return match ($mode) {
            'pos_code' => $posCode !== '' ? $posCode : ($posName !== '' ? $posName : 'Caja PDV'),
            'session_number' => $sessionNumber !== '' ? $sessionNumber : ('Sesión #' . ($orderRow->pos_session_id ?? '')),
            'hidden' => '',
            default => $employeeName !== '' ? $employeeName : 'Sin vendedor asignado',
        };
    }


    public function printPendingTicket(\Illuminate\Http\Request $request, int $order)
    {
        // V5.50.4A - permiso para imprimir tickets pendientes.
        abort_unless($this->v5504aCanPendingTicket('pos.pending_tickets.print'), 403, 'No tienes permiso para imprimir tickets pendientes.');

        if (! auth()->check()) {
            abort(401, 'Tu sesión expiró. Vuelve a iniciar sesión.');
        }

        $orderRow = \Illuminate\Support\Facades\DB::table('pos_orders')
            ->where('id', $order)
            ->first();

        if (! $orderRow) {
            abort(404, 'Ticket no encontrado.');
        }

        if ((string) ($orderRow->status ?? '') !== 'pending_payment') {
            abort(404, 'Este ticket ya no está pendiente de cobro.');
        }

        $metadata = [];

        if (! empty($orderRow->metadata)) {
            $decoded = json_decode((string) $orderRow->metadata, true);

            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        $isInitialPrint = $request->boolean('initial');
        $reprintCount = (int) ($metadata['pending_ticket_reprint_count'] ?? 0);

        if (! $isInitialPrint) {
            $reprintCount++;
            $metadata['pending_ticket_reprint_count'] = $reprintCount;
            $metadata['pending_ticket_last_reprinted_at'] = now()->toDateTimeString();

            \Illuminate\Support\Facades\DB::table('pos_orders')
                ->where('id', $orderRow->id)
                ->update([
                    'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);

            $orderRow->metadata = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $lines = \Illuminate\Support\Facades\DB::table('pos_order_lines as l')
            ->leftJoin('stock_serial_numbers as ss', 'ss.id', '=', 'l.stock_serial_number_id')
            ->where('l.pos_order_id', $orderRow->id)
            ->orderBy('l.id')
            ->select('l.*', 'ss.serial_number as line_serial_number')
            ->get();

        $employeeName = '';

        if (! empty($orderRow->employee_id)) {
            $employee = \Illuminate\Support\Facades\DB::table('employees')
                ->where('id', $orderRow->employee_id)
                ->first();

            $employeeName = (string) ($employee->name ?? '');
        }

        $posRow = null;

        if (! empty($orderRow->pos_point_id)) {
            $posRow = \Illuminate\Support\Facades\DB::table('pos_points')
                ->where('id', $orderRow->pos_point_id)
                ->first();
        }

        $logoUrl = null;

        if ($posRow) {
            foreach ([
                'ticket_logo_path',
                'receipt_logo_path',
                'invoice_logo_path',
                'logo_path',
                'logo',
                'image_path',
            ] as $field) {
                if (isset($posRow->{$field}) && trim((string) $posRow->{$field}) !== '') {
                    $rawLogo = trim((string) $posRow->{$field});

                    if (str_starts_with($rawLogo, 'http://') || str_starts_with($rawLogo, 'https://')) {
                        $logoUrl = $rawLogo;
                    } else {
                        $rawLogo = ltrim($rawLogo, '/');

                        if (str_starts_with($rawLogo, 'storage/')) {
                            $logoUrl = asset($rawLogo);
                        } else {
                            $logoUrl = asset('storage/' . $rawLogo);
                        }
                    }

                    break;
                }
            }
        }

        return view('pos.pending-ticket-print', [
            'order' => $orderRow,
            'lines' => $lines,
            'sellerName' => $this->receiptSellerDisplayName($posRow, $orderRow, $employeeName),
            'customerName' => ($this->v5403CustomerPayloadFromOrder($orderRow)['name'] ?? 'Público en General'),
            'logoUrl' => $logoUrl,
            'printedAt' => now(),
            'reprintCount' => $reprintCount ?? 0,
        ]);
    }
    public function printPaidTicket(\Illuminate\Http\Request $request, int $order)
    {
        if (! auth()->check()) {
            abort(401, 'Tu sesión expiró. Vuelve a iniciar sesión.');
        }

        $orderRow = \Illuminate\Support\Facades\DB::table('pos_orders')
            ->where('id', $order)
            ->first();

        if (! $orderRow) {
            abort(404, 'Ticket no encontrado.');
        }

        if (! in_array((string) ($orderRow->status ?? ''), ['paid', 'returned'], true)) {
            abort(404, 'Este ticket no está pagado o devuelto.');
        }

        $metadata = [];

        if (! empty($orderRow->metadata)) {
            $decoded = json_decode((string) $orderRow->metadata, true);

            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        $lines = \Illuminate\Support\Facades\DB::table('pos_order_lines as l')
            ->leftJoin('stock_serial_numbers as ss', 'ss.id', '=', 'l.stock_serial_number_id')
            ->where('l.pos_order_id', $orderRow->id)
            ->orderBy('l.id')
            ->select('l.*', 'ss.serial_number as line_serial_number')
            ->get();

        $payments = \Illuminate\Support\Facades\DB::table('pos_order_payments')
            ->where('pos_order_id', $orderRow->id)
            ->orderBy('id')
            ->get();

        $employeeName = '';

        if (! empty($orderRow->employee_id)) {
            $employee = \Illuminate\Support\Facades\DB::table('employees')
                ->where('id', $orderRow->employee_id)
                ->first();

            $employeeName = (string) ($employee->name ?? '');
        }

        $posRow = null;

        if (! empty($orderRow->pos_point_id)) {
            $posRow = \Illuminate\Support\Facades\DB::table('pos_points')
                ->where('id', $orderRow->pos_point_id)
                ->first();
        }

        $logoUrl = null;

        if ($posRow) {
            foreach ([
                'ticket_logo_path',
                'receipt_logo_path',
                'invoice_logo_path',
                'logo_path',
                'logo',
                'image_path',
            ] as $field) {
                if (isset($posRow->{$field}) && trim((string) $posRow->{$field}) !== '') {
                    $rawLogo = trim((string) $posRow->{$field});

                    if (str_starts_with($rawLogo, 'http://') || str_starts_with($rawLogo, 'https://')) {
                        $logoUrl = $rawLogo;
                    } else {
                        $rawLogo = ltrim($rawLogo, '/');

                        if (str_starts_with($rawLogo, 'storage/')) {
                            $logoUrl = asset($rawLogo);
                        } else {
                            $logoUrl = asset('storage/' . $rawLogo);
                        }
                    }

                    break;
                }
            }
        }

        /*
         * BEXIA_V5528D2_QR_FACTURACION_TICKET_TOTAL_PROD
         * El QR del ticket cobrado debe abrir el portal publico de facturacion
         * con folio y total precargados para facilitar la autofacturacion.
         */
        $invoicePortalTotal = null;

        foreach (['total', 'grand_total', 'paid_total', 'amount_total', 'total_amount'] as $totalField) {
            if (isset($orderRow->{$totalField}) && $orderRow->{$totalField} !== null) {
                $invoicePortalTotal = number_format((float) $orderRow->{$totalField}, 2, '.', '');
                break;
            }
        }

        $invoiceUrl = route('public.invoice-placeholder') . '?' . http_build_query(array_filter([
            'ticket' => (string) ($orderRow->number ?? ''),
            'total' => $invoicePortalTotal,
        ], fn ($value): bool => $value !== null && $value !== ''));

        return view('pos.paid-ticket-print', [
            'order' => $orderRow,
            'lines' => $lines,
            'payments' => $payments,
            'sellerName' => $this->receiptSellerDisplayName($posRow, $orderRow, $employeeName),
            'customerName' => ($this->v5403CustomerPayloadFromOrder($orderRow)['name'] ?? 'Público en General'),
            'logoUrl' => $logoUrl,
            'printedAt' => now(),
            'metadata' => $metadata,
            'invoiceUrl' => $invoiceUrl,
        ]);
    }







    public function searchPendingOrder(\Illuminate\Http\Request $request, int $session)
    {
        // V5.50.4A - permiso searchPendingOrder.
        if (! $this->v5504aCanPendingTicket('pos.pending_tickets.view')) {
            return $this->v5504aPendingTicketForbiddenJson('No tienes permiso para buscar tickets pendientes.');
        }

        if (! auth()->check()) {
            return response()->json([
                'ok' => false,
                'message' => 'Tu sesión expiró. Vuelve a iniciar sesión.',
            ], 401);
        }

        $ticket = trim((string) ($request->query('ticket') ?? $request->query('folio') ?? ''));

        $ticket = str_replace(["'", "’", "`", "´", " ", "_"], '-', $ticket);
        $ticket = preg_replace('/-+/', '-', $ticket);
        $ticket = strtoupper(trim($ticket, '-'));

        if (preg_match('/^([A-Z]+)(\d{8})(\d{5})$/', $ticket, $matches)) {
            $ticket = $matches[1] . '-' . $matches[2] . '-' . $matches[3];
        }

        if ($ticket === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Escanea el QR o escribe el número de ticket.',
            ], 422);
        }

        $sessionRow = \Illuminate\Support\Facades\DB::table('pos_sessions')
            ->where('id', $session)
            ->first();

        if (! $sessionRow) {
            return response()->json([
                'ok' => false,
                'message' => 'No se encontró la sesión del PDV.',
            ], 404);
        }

        $query = \Illuminate\Support\Facades\DB::table('pos_orders')
            ->where('status', 'pending_payment')
            ->where('number', $ticket);

        if (! empty($sessionRow->company_id)) {
            $query->where('company_id', $sessionRow->company_id);
        }

        if (! empty($sessionRow->pos_point_id)) {
            $query->where('pos_point_id', $sessionRow->pos_point_id);
        }

        $order = $query->first();

        if (! $order) {
            return response()->json([
                'ok' => false,
                'message' => 'No se encontró un ticket pendiente con ese número.',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'order' => $this->v5403PendingOrderPayload($order, true),
        ]);
    }




    public function posCustomers(\Illuminate\Http\Request $request, int $session)
    {
        if (! auth()->check()) {
            return response()->json([
                'ok' => false,
                'message' => 'Tu sesión expiró. Vuelve a iniciar sesión.',
            ], 401);
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('contacts')) {
            return response()->json([
                'ok' => true,
                'customers' => [],
            ]);
        }

        $sessionRow = \Illuminate\Support\Facades\DB::table('pos_sessions')
            ->where('id', $session)
            ->first();

        if (! $sessionRow) {
            return response()->json([
                'ok' => false,
                'message' => 'No se encontró la sesión del PDV.',
            ], 404);
        }

        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('contacts');

        $has = function (string $column) use ($columns): bool {
            return in_array($column, $columns, true);
        };

        $nameColumns = array_values(array_filter([
            $has('name') ? 'name' : null,
            $has('commercial_name') ? 'commercial_name' : null,
            $has('business_name') ? 'business_name' : null,
            $has('display_name') ? 'display_name' : null,
            $has('legal_name') ? 'legal_name' : null,
        ]));

        if (empty($nameColumns)) {
            $nameColumns = ['id'];
        }

        $query = \Illuminate\Support\Facades\DB::table('contacts');

        if (! empty($sessionRow->company_id) && $has('company_id')) {
            $query->where('company_id', $sessionRow->company_id);
        }

        if ($has('active')) {
            $query->where(function ($q) {
                $q->where('active', true)->orWhereNull('active');
            });
        }

        $customerFlags = array_values(array_filter([
            $has('is_customer') ? 'is_customer' : null,
            $has('customer') ? 'customer' : null,
            $has('is_client') ? 'is_client' : null,
            $has('client') ? 'client' : null,
        ]));

        $supplierFlags = array_values(array_filter([
            $has('is_supplier') ? 'is_supplier' : null,
            $has('supplier') ? 'supplier' : null,
            $has('is_vendor') ? 'is_vendor' : null,
            $has('vendor') ? 'vendor' : null,
        ]));

        if (! empty($customerFlags)) {
            $query->where(function ($q) use ($customerFlags) {
                foreach ($customerFlags as $flag) {
                    $q->orWhere($flag, true);
                }
            });
        } elseif (! empty($supplierFlags)) {
            $query->where(function ($q) use ($supplierFlags) {
                foreach ($supplierFlags as $flag) {
                    $q->where(function ($sub) use ($flag) {
                        $sub->where($flag, false)->orWhereNull($flag);
                    });
                }
            });
        }

        $search = trim((string) $request->query('q', ''));

        if ($search !== '') {
            $like = '%' . mb_strtolower($search) . '%';

            $query->where(function ($q) use ($like, $nameColumns, $has) {
                foreach ($nameColumns as $column) {
                    if ($column !== 'id') {
                        $q->orWhereRaw("lower(coalesce({$column}, '')) like ?", [$like]);
                    }
                }

                if ($has('rfc')) {
                    $q->orWhereRaw("lower(coalesce(rfc, '')) like ?", [$like]);
                }

                if ($has('email')) {
                    $q->orWhereRaw("lower(coalesce(email, '')) like ?", [$like]);
                }
            });
        }

        foreach ($nameColumns as $column) {
            if ($column !== 'id') {
                $query->orderBy($column);
                break;
            }
        }

        $customers = $query
            ->limit(50)
            ->get()
            ->map(function ($contact) use ($nameColumns) {
                $name = '';

                foreach ($nameColumns as $column) {
                    if ($column !== 'id' && isset($contact->{$column}) && trim((string) $contact->{$column}) !== '') {
                        $name = trim((string) $contact->{$column});
                        break;
                    }
                }

                if ($name === '') {
                    $name = 'Cliente #' . $contact->id;
                }

                return [
                    'id' => (int) $contact->id,
                    'name' => $name,
                    'rfc' => (string) ($contact->rfc ?? ''),
                    'email' => (string) ($contact->email ?? ''),
                    'phone' => (string) ($contact->phone ?? ''),
                ];
            })
            ->values();

        return response()->json([
            'ok' => true,
            'customers' => $customers,
        ]);
    }



    protected function v5393CustomerPayloadFromOrder(object $order): ?array
    {
        $customerId = $order->customer_id ?? $order->contact_id ?? null;

        if (empty($customerId) || ! \Illuminate\Support\Facades\Schema::hasTable('contacts')) {
            return null;
        }

        $contact = \Illuminate\Support\Facades\DB::table('contacts')
            ->where('id', $customerId)
            ->first();

        if (! $contact) {
            return null;
        }

        $name = '';

        foreach (['name', 'commercial_name', 'business_name', 'display_name', 'legal_name'] as $field) {
            if (isset($contact->{$field}) && trim((string) $contact->{$field}) !== '') {
                $name = trim((string) $contact->{$field});
                break;
            }
        }

        if ($name === '') {
            $name = 'Cliente #' . $contact->id;
        }

        return [
            'id' => (int) $contact->id,
            'name' => $name,
            'rfc' => (string) ($contact->rfc ?? ''),
            'email' => (string) ($contact->email ?? ''),
            'phone' => (string) ($contact->phone ?? ''),
        ];
    }



    protected function v5394ValidatedCustomerIdFromRequest(\Illuminate\Http\Request $request, object $sessionRow = null): ?int
    {
        $customerId = $request->input('customer_id');

        if ($customerId === null || $customerId === '' || (int) $customerId <= 0) {
            return null;
        }

        $customerId = (int) $customerId;

        if (! \Illuminate\Support\Facades\Schema::hasTable('contacts')) {
            return null;
        }

        $query = \Illuminate\Support\Facades\DB::table('contacts')
            ->where('id', $customerId);

        if (
            $sessionRow
            && ! empty($sessionRow->company_id)
            && \Illuminate\Support\Facades\Schema::hasColumn('contacts', 'company_id')
        ) {
            $query->where('company_id', $sessionRow->company_id);
        }

        return $query->exists() ? $customerId : null;
    }



    protected function v5395CustomerPayload(?int $customerId): ?array
    {
        if (empty($customerId) || ! \Illuminate\Support\Facades\Schema::hasTable('contacts')) {
            return null;
        }

        $contact = \Illuminate\Support\Facades\DB::table('contacts')
            ->where('id', $customerId)
            ->first();

        if (! $contact) {
            return null;
        }

        $name = '';

        foreach (['name', 'commercial_name', 'business_name', 'display_name', 'legal_name'] as $field) {
            if (isset($contact->{$field}) && trim((string) $contact->{$field}) !== '') {
                $name = trim((string) $contact->{$field});
                break;
            }
        }

        if ($name === '') {
            $name = 'Cliente #' . $contact->id;
        }

        return [
            'id' => (int) $contact->id,
            'name' => $name,
            'rfc' => (string) ($contact->rfc ?? ''),
            'email' => (string) ($contact->email ?? ''),
            'phone' => (string) ($contact->phone ?? ''),
        ];
    }

    protected function v5395CustomerPayloadFromOrder(object $order): ?array
    {
        return $this->v5395CustomerPayload(isset($order->customer_id) ? (int) $order->customer_id : null);
    }



    protected function v5403ContactName(object $contact): string
    {
        foreach (['name', 'commercial_name', 'business_name', 'display_name', 'legal_name'] as $field) {
            if (isset($contact->{$field}) && trim((string) $contact->{$field}) !== '') {
                return trim((string) $contact->{$field});
            }
        }

        return 'Cliente #' . ($contact->id ?? '');
    }

    protected function v5403CustomerPayloadFromOrder(object $order): ?array
    {
        $customerId = $order->customer_id ?? null;

        if (empty($customerId) || ! \Illuminate\Support\Facades\Schema::hasTable('contacts')) {
            return null;
        }

        $contact = \Illuminate\Support\Facades\DB::table('contacts')
            ->where('id', (int) $customerId)
            ->first();

        if (! $contact) {
            return null;
        }

        return [
            'id' => (int) $contact->id,
            'name' => $this->v5403ContactName($contact),
            'rfc' => (string) ($contact->rfc ?? ''),
            'email' => (string) ($contact->email ?? ''),
            'phone' => (string) ($contact->phone ?? ''),
        ];
    }

    protected function v5403SellerNameFromOrder(object $order): string
    {
        if (empty($order->employee_id) || ! \Illuminate\Support\Facades\Schema::hasTable('employees')) {
            return 'Sin vendedor asignado';
        }

        $employee = \Illuminate\Support\Facades\DB::table('employees')
            ->where('id', (int) $order->employee_id)
            ->first();

        if ($employee && isset($employee->name) && trim((string) $employee->name) !== '') {
            return trim((string) $employee->name);
        }

        return 'Sin vendedor asignado';
    }

    protected function v5403LinesForPendingOrder(int $orderId)
    {
        return \Illuminate\Support\Facades\DB::table('pos_order_lines')
            ->where('pos_order_id', $orderId)
            ->orderBy('id')
            ->get()
            ->map(function ($line) {
                return [
                    'id' => (int) $line->id,
                    'product_id' => $line->product_id ? (int) $line->product_id : null,
                    'product_variant_id' => ! empty($line->product_variant_id) ? (int) $line->product_variant_id : null,
                    'stock_serial_number_id' => ! empty($line->stock_serial_number_id) ? (int) $line->stock_serial_number_id : null,
                    'stock_lot_id' => ! empty($line->stock_lot_id) ? (int) $line->stock_lot_id : null,
                    'lot_number' => ! empty($line->stock_lot_id) && \Illuminate\Support\Facades\Schema::hasTable('stock_lots')
                        ? (string) (\Illuminate\Support\Facades\DB::table('stock_lots')->where('id', (int) $line->stock_lot_id)->value('lot_number') ?? '')
                        : '',
                    'lot_locked_from_pending' => ! empty($line->stock_lot_id),
                    'serial_number' => ! empty($line->stock_serial_number_id) && \Illuminate\Support\Facades\Schema::hasTable('stock_serial_numbers')
                        ? (string) (\Illuminate\Support\Facades\DB::table('stock_serial_numbers')->where('id', (int) $line->stock_serial_number_id)->value('serial_number') ?? '')
                        : '',
                    'serial_locked_from_pending' => ! empty($line->stock_serial_number_id),
                    // BEXIA_V5544D_PENDING_SERIAL_LOCK_PAYLOAD
                    'name' => (string) ($line->product_name ?? 'Producto'),
                    'reference' => (string) ($line->product_reference ?? ''),
                    'qty' => (float) ($line->quantity ?? 0),
                    'price' => (float) ($line->unit_price ?? 0),
                    'tax_rate' => (float) ($line->tax_rate ?? 0),
                    'line_total' => (float) ($line->total ?? 0),
                    'total' => (float) ($line->total ?? 0),
                ];
            })
            ->values();
    }

    protected function v5403PendingOrderPayload(object $order, bool $withLines = true): array
    {
        $customer = $this->v5403CustomerPayloadFromOrder($order);

        return [
            'id' => (int) $order->id,
            'number' => (string) $order->number,
            'status' => (string) ($order->status ?? 'pending_payment'),
            'status_label' => 'Pendiente de cobro',
            'total' => (float) ($order->total ?? 0),
            'price_list_id' => $this->v5496aPriceListPayloadFromOrder($order)['price_list_id'] ?? null,
            'price_list_name' => $this->v5496aPriceListPayloadFromOrder($order)['price_list_name'] ?? null,
            'seller_name' => $this->v5403SellerNameFromOrder($order),
            'customer_id' => $customer['id'] ?? null,
            'customer' => $customer,
            'items' => $withLines ? $this->v5403LinesForPendingOrder((int) $order->id) : [],
        ];
    }

    protected function releaseSalesQuoteAfterPendingTicketCancelled(int $posOrderId, ?int $userId = null): void
    {
        if ($posOrderId <= 0) {
            return;
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('sales_quote_pos_tickets')) {
            return;
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('sales_orders')) {
            return;
        }

        $bridge = \Illuminate\Support\Facades\DB::table('sales_quote_pos_tickets')
            ->where('pos_order_id', $posOrderId)
            ->orderByDesc('id')
            ->first();

        if (! $bridge) {
            return;
        }

        if ((string) ($bridge->status ?? '') === 'paid' || ! empty($bridge->paid_at)) {
            return;
        }

        $now = now();

        $metadata = [];
        if (! empty($bridge->metadata)) {
            $decoded = json_decode((string) $bridge->metadata, true);
            $metadata = is_array($decoded) ? $decoded : [];
        }

        $metadata['cancelled_from_pos'] = true;
        $metadata['cancelled_from_pos_at'] = $now->toDateTimeString();
        $metadata['cancelled_from_pos_by_user_id'] = $userId;
        $metadata['quote_released'] = true;

        $notes = trim((string) ($bridge->notes ?? ''));
        $releaseNote = 'Ticket PDV cancelado desde PDV. Cotización liberada para reenviar a PDV o convertir a orden de venta.';
        $notes = $notes !== '' ? trim($notes . "\n" . $releaseNote) : $releaseNote;

        \Illuminate\Support\Facades\DB::table('sales_quote_pos_tickets')
            ->where('id', (int) $bridge->id)
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => $now,
                'notes' => $notes,
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => $now,
            ]);

        $quoteUpdate = [
            'updated_at' => $now,
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('sales_orders', 'quote_pos_payment_status')) {
            $quoteUpdate['quote_pos_payment_status'] = null;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('sales_orders', 'quote_pos_order_id')) {
            $quoteUpdate['quote_pos_order_id'] = null;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('sales_orders', 'quote_pos_paid_at')) {
            $quoteUpdate['quote_pos_paid_at'] = null;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('sales_orders', 'quote_validation_message')) {
            $quoteUpdate['quote_validation_message'] = $releaseNote;
        }

        \Illuminate\Support\Facades\DB::table('sales_orders')
            ->where('id', (int) $bridge->sales_order_id)
            ->update($quoteUpdate);
    }




    public function cancelPendingOrder(\Illuminate\Http\Request $request, int $order)
    {
        // V5.51.5B - Audit intento cancelar ticket pendiente con empresa/sesión.
        $v5515bPendingOrderForAudit = null;

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('pos_orders')) {
                $v5515bPendingOrderForAudit = \Illuminate\Support\Facades\DB::table('pos_orders')
                    ->where('id', $order)
                    ->first();
            }
        } catch (\Throwable $e) {
            $v5515bPendingOrderForAudit = null;
        }

        $this->v5515aWritePosAuditLog('pos.ticket.cancel_pending.attempt', [
            'company_id' => $v5515bPendingOrderForAudit->company_id ?? null,
            'pos_session_id' => $v5515bPendingOrderForAudit->pos_session_id ?? null,
            'pos_order_id' => $order,
            'entity_type' => 'pos_order',
            'entity_id' => $order,
            'description' => 'Intento de cancelación de ticket pendiente.',
            'before_data' => $v5515bPendingOrderForAudit ? [
                'number' => $v5515bPendingOrderForAudit->number ?? null,
                'status' => $v5515bPendingOrderForAudit->status ?? null,
                'total' => $v5515bPendingOrderForAudit->total ?? null,
            ] : null,
            'metadata' => [
                'route' => optional($request->route())->getName(),
                'method' => $request->method(),
            ],
        ]);


        // V5.51.4B - Permiso backend cancelar ticket pendiente.
        if (! $this->v5514bCanCancelPendingPosTicket()) {
            return $this->v5514bForbiddenPosResponse($request, 'No tienes permiso para cancelar tickets pendientes.');
        }


        abort_unless(auth()->check(), 403);

        if (! $this->v5514bCanCancelPendingPosTicket()) {
            return $this->v5504aPendingTicketForbiddenJson('No tienes permiso para cancelar tickets pendientes.');
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('pos_orders')) {
            return response()->json([
                'ok' => false,
                'message' => 'No existe la tabla de órdenes POS.',
            ], 500);
        }

        $reason = trim((string) $request->input('reason', ''));

        if ($reason === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Indica el motivo de cancelación.',
            ], 422);
        }

        $result = \Illuminate\Support\Facades\DB::transaction(function () use ($order, $reason) {
            $orderRow = \Illuminate\Support\Facades\DB::table('pos_orders')
                ->where('id', $order)
                ->lockForUpdate()
                ->first();

            if (! $orderRow) {
                return [
                    'ok' => false,
                    'status' => 404,
                    'message' => 'No se encontró el ticket.',
                ];
            }

            if ((string) ($orderRow->status ?? '') !== 'pending_payment') {
                return [
                    'ok' => false,
                    'status' => 422,
                    'message' => 'Solo se pueden cancelar tickets pendientes.',
                ];
            }

            $metadata = [];

            if (! empty($orderRow->metadata)) {
                $decoded = json_decode((string) $orderRow->metadata, true);
                $metadata = is_array($decoded) ? $decoded : [];
            }

            $metadata['cancelled'] = true;
            $metadata['cancelled_at'] = now()->toDateTimeString();
            $metadata['cancelled_by_user_id'] = auth()->id();
            $metadata['cancel_reason'] = $reason;
            $metadata['cancel_source'] = 'pos_pending_ticket_modal';

            $update = [
                'status' => 'cancelled',
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ];

            foreach ([
                'cancelled_at' => now(),
                'cancelled_by_user_id' => auth()->id(),
                'cancel_reason' => $reason,
            ] as $column => $value) {
                if (\Illuminate\Support\Facades\Schema::hasColumn('pos_orders', $column)) {
                    $update[$column] = $value;
                }
            }

            \Illuminate\Support\Facades\DB::table('pos_orders')
                ->where('id', $orderRow->id)
                ->update($update);

            return [
                'ok' => true,
                'status' => 200,
                'order_id' => (int) $orderRow->id,
                'number' => (string) $orderRow->number,
            ];
        });

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'message' => $result['message'] ?? 'No se pudo cancelar el ticket pendiente.',
            ], $result['status'] ?? 500);
        }

                $this->releaseSalesQuoteAfterPendingTicketCancelled((int) $order, auth()->id());

                // V5.51.5B - Audit éxito cancelar ticket pendiente con empresa/sesión.
        $this->v5515aWritePosAuditLog('pos.ticket.cancel_pending.success', [
            'company_id' => $v5515bPendingOrderForAudit->company_id ?? null,
            'pos_session_id' => $v5515bPendingOrderForAudit->pos_session_id ?? null,
            'pos_order_id' => $order,
            'entity_type' => 'pos_order',
            'entity_id' => $order,
            'description' => 'Ticket pendiente cancelado.',
            'before_data' => $v5515bPendingOrderForAudit ? [
                'number' => $v5515bPendingOrderForAudit->number ?? null,
                'status' => $v5515bPendingOrderForAudit->status ?? null,
                'total' => $v5515bPendingOrderForAudit->total ?? null,
            ] : null,
            'after_data' => [
                'status' => 'cancelled',
            ],
            'metadata' => [
                'route' => optional($request->route())->getName(),
                'method' => $request->method(),
            ],
        ]);

return response()->json([
            'ok' => true,
            'message' => 'Ticket pendiente cancelado correctamente.',
            'order_id' => $result['order_id'],
            'number' => $result['number'],
        ]);
    }

    // V5_53_0C_pos_cash_change_dev
    public function payOrder(\Illuminate\Http\Request $request, int $order)
    {
        if (! auth()->check()) {
            return response()->json([
                'ok' => false,
                'message' => 'Tu sesión expiró. Vuelve a iniciar sesión.',
            ], 401);
        }

        foreach (['pos_orders', 'pos_order_payments'] as $table) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                return response()->json([
                    'ok' => false,
                    'message' => "No existe la tabla {$table}.",
                ], 500);
            }
        }

        $payments = $request->input('payments', []);

        if (! is_array($payments) || count($payments) === 0) {
            $payments = [[
                'payment_form_id' => $request->input('payment_form_id'),
                'payment_label' => $request->input('payment_label', 'Pago'),
                'amount' => $request->input('amount'),
            ]];
        }

        $result = \Illuminate\Support\Facades\DB::transaction(function () use ($order, $payments) {
            $orderRow = \Illuminate\Support\Facades\DB::table('pos_orders')
                ->where('id', $order)
                ->lockForUpdate()
                ->first();

            if (! $orderRow) {
                return [
                    'ok' => false,
                    'status' => 404,
                    'message' => 'No se encontró el ticket.',
                ];
            }

            if ((string) $orderRow->status !== 'pending_payment') {
                return [
                    'ok' => false,
                    'status' => 422,
                    'message' => 'Este ticket ya no está pendiente de cobro.',
                ];
            }

            $requestedTotal = $this->v5481jApplyPendingPaymentAdjustments($orderRow, request());
            $total = round((float) $requestedTotal, 2);

            $normalized = [];
            $tenderedSum = 0.0;
            $appliedSum = 0.0;
            $cashIndexes = [];

            foreach ($payments as $payment) {
                $tenderedAmount = round((float) ($payment['amount'] ?? 0), 2);
                $paymentFormId = $payment['payment_form_id'] ?? null;
                $paymentLabel = trim((string) ($payment['payment_label'] ?? ''));

                if ($tenderedAmount <= 0) {
                    return [
                        'ok' => false,
                        'status' => 422,
                        'message' => 'Todos los pagos deben tener un importe mayor a cero.',
                    ];
                }

                if ($paymentFormId !== null && $paymentFormId !== '' && ! is_numeric($paymentFormId)) {
                    return [
                        'ok' => false,
                        'status' => 422,
                        'message' => 'Forma de pago inválida.',
                    ];
                }

                $paymentFormId = $paymentFormId !== null && $paymentFormId !== '' ? (int) $paymentFormId : null;
                $paymentForm = null;

                if ($paymentFormId && \Illuminate\Support\Facades\Schema::hasTable('payment_forms')) {
                    $query = \Illuminate\Support\Facades\DB::table('payment_forms')
                        ->where('id', $paymentFormId);

                    if (\Illuminate\Support\Facades\Schema::hasColumn('payment_forms', 'is_active')) {
                        $query->where('is_active', true);
                    }

                    if (! empty($orderRow->company_id) && \Illuminate\Support\Facades\Schema::hasColumn('payment_forms', 'company_id')) {
                        $query->where('company_id', $orderRow->company_id);
                    }

                    $paymentForm = $query->first();

                    if (! $paymentForm) {
                        return [
                            'ok' => false,
                            'status' => 422,
                            'message' => 'Una forma de pago no corresponde a esta empresa o está inactiva.',
                        ];
                    }

                    if ($paymentLabel === '') {
                        $paymentLabel = (string) ($paymentForm->name ?? 'Pago');
                    }
                }

                if ($paymentLabel === '') {
                    $paymentLabel = 'Pago';
                }

                $paymentFormCode = $paymentForm->code ?? null;
                $labelForCash = strtolower($paymentLabel . ' ' . (string) $paymentFormCode);
                $isCash = isset($paymentForm->is_cash)
                    ? (bool) $paymentForm->is_cash
                    : (str_contains($labelForCash, 'efectivo') || str_contains($labelForCash, 'cash') || trim((string) $paymentFormCode) === '01');

                $rowIndex = count($normalized);

                $normalized[] = [
                    'payment_form_id' => $paymentFormId,
                    'payment_label' => $paymentLabel,
                    'amount' => $tenderedAmount,
                    'tendered_amount' => $tenderedAmount,
                    'cash_received' => $isCash ? $tenderedAmount : null,
                    'change_amount' => 0.0,
                    'payment_form_code' => $paymentFormCode,
                    'is_cash' => $isCash,
                    'is_credit' => isset($paymentForm->is_credit) ? (bool) $paymentForm->is_credit : null,
                ];

                $tenderedSum += $tenderedAmount;

                if ($isCash) {
                    $cashIndexes[] = $rowIndex;
                }
            }

            $tenderedSum = round($tenderedSum, 2);

            if ($tenderedSum + 0.01 < $total) {
                return [
                    'ok' => false,
                    'status' => 422,
                    'message' => 'El pago recibido es menor al total del ticket. Total: $' . number_format($total, 2) . ' / Recibido: $' . number_format($tenderedSum, 2),
                ];
            }

            $overage = round($tenderedSum - $total, 2);

            if ($overage > 0.01 && empty($cashIndexes)) {
                return [
                    'ok' => false,
                    'status' => 422,
                    'message' => 'Solo el pago en efectivo puede ser mayor al total para calcular cambio. Total: $' . number_format($total, 2) . ' / Recibido: $' . number_format($tenderedSum, 2),
                ];
            }

            if ($overage > 0.01) {
                $cashIndex = end($cashIndexes);
                $cashReceived = round((float) ($normalized[$cashIndex]['tendered_amount'] ?? 0), 2);
                $cashApplied = round($cashReceived - $overage, 2);

                if ($cashApplied <= 0) {
                    return [
                        'ok' => false,
                        'status' => 422,
                        'message' => 'El excedente de efectivo es mayor al pago efectivo recibido. Revisa los importes.',
                    ];
                }

                $normalized[$cashIndex]['amount'] = $cashApplied;
                $normalized[$cashIndex]['cash_received'] = $cashReceived;
                $normalized[$cashIndex]['change_amount'] = $overage;
            }

            foreach ($normalized as $payment) {
                $appliedSum += (float) ($payment['amount'] ?? 0);
            }

            $appliedSum = round($appliedSum, 2);

            if (abs($appliedSum - $total) > 0.01) {
                return [
                    'ok' => false,
                    'status' => 422,
                    'message' => 'La suma aplicada debe ser igual al total del ticket. Total: $' . number_format($total, 2) . ' / Aplicado: $' . number_format($appliedSum, 2),
                ];
            }

            $cashReceivedTotal = round(array_sum(array_map(fn ($payment) => (float) ($payment['cash_received'] ?? 0), $normalized)), 2);
            $changeTotal = round(array_sum(array_map(fn ($payment) => (float) ($payment['change_amount'] ?? 0), $normalized)), 2);

            \Illuminate\Support\Facades\DB::table('pos_order_payments')
                ->where('pos_order_id', $orderRow->id)
                ->delete();

            foreach ($normalized as $paymentIndex => $payment) {
                $paymentId = \Illuminate\Support\Facades\DB::table('pos_order_payments')->insertGetId([
                    'pos_order_id' => $orderRow->id,
                    'payment_form_id' => $payment['payment_form_id'],
                    'payment_label' => $payment['payment_label'],
                    'amount' => $payment['amount'],
                    'status' => 'paid',
                    'metadata' => json_encode([
                        'source' => 'pos_frontend',
                        'paid_by_user_id' => auth()->id(),
                        'session_id' => $orderRow->pos_session_id ?? null,
                        'payment_form_code' => $payment['payment_form_code'],
                        'is_cash' => $payment['is_cash'],
                        'is_credit' => $payment['is_credit'],
                        'tendered_amount' => $payment['tendered_amount'] ?? $payment['amount'],
                        'cash_received' => $payment['cash_received'] ?? null,
                        'change_amount' => $payment['change_amount'] ?? 0,
                        'amount_applied_to_sale' => $payment['amount'],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                /*
                 * V5.69.0d5:
                 * Si el pago es efectivo, se registra inmediatamente una entrada
                 * posted en Tesoreria contra la Caja PDV asociada al punto de venta.
                 */
                if ((bool) ($payment['is_cash'] ?? false)) {
                    $treasuryResult = $this->v5690d5PostCashPaymentToTreasury($orderRow, (int) $paymentId, $payment);

                    if (! ($treasuryResult['ok'] ?? false)) {
                        return [
                            'ok' => false,
                            'status' => 422,
                            'message' => $treasuryResult['message'] ?? 'No se pudo registrar el efectivo en Caja PDV.',
                        ];
                    }

                    $normalized[$paymentIndex]['treasury_account_id'] = $treasuryResult['treasury_account_id'] ?? null;
                    $normalized[$paymentIndex]['treasury_movement_id'] = $treasuryResult['treasury_movement_id'] ?? null;
                    $normalized[$paymentIndex]['treasury_posted_at'] = $treasuryResult['treasury_posted_at'] ?? null;
                }
            }

            $metadata = [];

            if (! empty($orderRow->metadata)) {
                $decoded = json_decode((string) $orderRow->metadata, true);

                if (is_array($decoded)) {
                    $metadata = $decoded;
                }
            }

            // V5.49.8C - asegurar columnas de lista de precios al cobrar.
            $v5498cPriceListId = $orderRow->price_list_id
                ?? ($metadata['price_list_id'] ?? null)
                ?? ($metadata['selected_price_list_id'] ?? null)
                ?? request()->input('price_list_id', request()->input('selected_price_list_id', null));

            $v5498cPriceListName = trim((string) (
                $orderRow->price_list_name
                ?? ($metadata['price_list_name'] ?? null)
                ?? ($metadata['selected_price_list_name'] ?? null)
                ?? request()->input('price_list_name', request()->input('selected_price_list_name', ''))
            ));

            $v5498cPriceListId = is_numeric($v5498cPriceListId) ? (int) $v5498cPriceListId : null;

            if ($v5498cPriceListName === '' && $v5498cPriceListId) {
                $v5498cPriceListName = 'Lista #' . $v5498cPriceListId;
            }

            if ($v5498cPriceListId) {
                $metadata['price_list_id'] = $v5498cPriceListId;
            }

            if ($v5498cPriceListName !== '') {
                $metadata['price_list_name'] = $v5498cPriceListName;
            }

            $metadata['paid'] = true;
            $metadata['payment_count'] = count($normalized);
            $metadata['paid_by_user_id'] = auth()->id();
            $metadata['payment_tendered_total'] = $tenderedSum;
            $metadata['payment_applied_total'] = $appliedSum;
            $metadata['cash_received_total'] = $cashReceivedTotal;
            $metadata['change_amount_total'] = $changeTotal;

            $v5498cOrderUpdate = [
                'status' => 'paid',
                'paid_at' => now(),
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ];

            if (\Illuminate\Support\Facades\Schema::hasColumn('pos_orders', 'price_list_id')) {
                $v5498cOrderUpdate['price_list_id'] = $v5498cPriceListId;
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn('pos_orders', 'price_list_name')) {
                $v5498cOrderUpdate['price_list_name'] = $v5498cPriceListName !== '' ? $v5498cPriceListName : null;
            }

            \Illuminate\Support\Facades\DB::table('pos_orders')
                ->where('id', $orderRow->id)
                ->update($v5498cOrderUpdate);

            $fresh = \Illuminate\Support\Facades\DB::table('pos_orders')
                ->where('id', $orderRow->id)
                ->first();

            return [
                'ok' => true,
                'status' => 200,
                'order' => $fresh,
                'payments' => $normalized,
                'total' => $total,
            ];
        });

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'message' => $result['message'] ?? 'No se pudo registrar el cobro.',
            ], $result['status'] ?? 500);
        }


        // V5.46.1 inventory poster: generar salida de inventario al cobrar.
        $v5461InventoryResult = app(\App\Support\PosInventoryPoster::class)->postPaidOrder((int) $order);

        
        // V5.61.4e: sincronizar cotizacion despues de salida PDV.
        try {
            app(\App\Support\Sales\QuotePosTicketSyncService::class)
                ->markPaidOnlyFromPosOrder((int) ((int) $order));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('No se pudo marcar cotizacion como cobrada en PDV despues de salida.', [
                'pos_order_id' => (int) ((int) $order),
                'error' => $e->getMessage(),
            ]);
        }

            // V5.61.2m: si este ticket viene de cotizacion, marcarla como Cobrado en PDV.
            try {
                $quotePosSyncOrderId = null;

                if (isset($orderId)) {
                    $quotePosSyncOrderId = (int) $orderId;
                } elseif (isset($order) && is_object($order) && isset($order->id)) {
                    $quotePosSyncOrderId = (int) $order->id;
                } elseif (isset($posOrder) && is_object($posOrder) && isset($posOrder->id)) {
                    $quotePosSyncOrderId = (int) $posOrder->id;
                }

                if ($quotePosSyncOrderId) {
                    app(\App\Support\Sales\QuotePosTicketSyncService::class)
                        ->markPaidFromPosOrder($quotePosSyncOrderId);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('No se pudo sincronizar cotizacion cobrada en PDV.', [
                    'error' => $e->getMessage(),
                ]);
            }

return response()->json([
            'ok' => true,
            'message' => 'Cobro registrado correctamente.',
            'order_id' => (int) $result['order']->id,
            'number' => (string) $result['order']->number,
            'status' => (string) $result['order']->status,
            'paid_at' => (string) $result['order']->paid_at,
            'total' => $result['total'],
            'payments' => $result['payments'],
            'print_url' => route('pos.orders.receipt.print', ['order' => $result['order']->id]),
        ]);
    }




    public function requestTicketBilling(\Illuminate\Http\Request $request, int $order)
    {
        $orderRow = \Illuminate\Support\Facades\DB::table('pos_orders')
            ->where('id', $order)
            ->first();

        abort_unless($orderRow, 404);

        if ((string) ($orderRow->status ?? '') !== 'paid') {
            return back()->with('error', 'Solo se pueden enviar a facturación tickets pagados.');
        }

        $metadata = [];

        if (! empty($orderRow->metadata)) {
            $decoded = json_decode((string) $orderRow->metadata, true);

            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        $metadata['billing_status'] = $metadata['billing_status'] ?? 'requested';
        $metadata['billing_requested_at'] = $metadata['billing_requested_at'] ?? now()->toDateTimeString();
        $metadata['billing_requested_by_user_id'] = $metadata['billing_requested_by_user_id'] ?? auth()->id();

        \Illuminate\Support\Facades\DB::table('pos_orders')
            ->where('id', $order)
            ->update([
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Ticket enviado a facturación.');
    }


    public function inventoryOutputPdf(\Illuminate\Http\Request $request, int $order)
    {
        abort_unless(class_exists(\Barryvdh\DomPDF\Facade\Pdf::class), 500, 'No hay motor PDF disponible.');

        $orderRow = \Illuminate\Support\Facades\DB::table('pos_orders')
            ->where('id', $order)
            ->first();

        abort_unless($orderRow, 404);

        $metadata = [];

        if (! empty($orderRow->metadata)) {
            $decoded = json_decode((string) $orderRow->metadata, true);

            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        $movementId = (int) ($metadata['stock_movement_id'] ?? 0);

        abort_if($movementId <= 0, 404, 'Este ticket no tiene salida de inventario asociada.');

        $movement = \Illuminate\Support\Facades\DB::table('stock_movements')
            ->where('id', $movementId)
            ->first();

        abort_unless($movement, 404);

        $movementLines = \Illuminate\Support\Facades\DB::table('stock_movement_lines')
            ->where('stock_movement_id', $movementId)
            ->orderBy('id')
            ->get();

        $productIds = $movementLines
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $products = collect();

        if ($productIds->isNotEmpty() && \Illuminate\Support\Facades\Schema::hasTable('products')) {
            $products = \Illuminate\Support\Facades\DB::table('products')
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');
        }

        $company = null;

        if (! empty($orderRow->company_id) && \Illuminate\Support\Facades\Schema::hasTable('companies')) {
            $company = \Illuminate\Support\Facades\DB::table('companies')
                ->where('id', (int) $orderRow->company_id)
                ->first();
        }

        $companyName = 'Bexia ERP';

        if ($company) {
            foreach (['commercial_name', 'business_name', 'name', 'legal_name'] as $column) {
                if (isset($company->{$column}) && trim((string) $company->{$column}) !== '') {
                    $companyName = trim((string) $company->{$column});
                    break;
                }
            }
        }

        $logoSrc = null;

        if ($company) {
            foreach (['logo_path', 'logo', 'image_path', 'logo_url'] as $column) {
                if (! isset($company->{$column}) || trim((string) $company->{$column}) === '') {
                    continue;
                }

                $rawLogo = trim((string) $company->{$column});
                $rawLogo = ltrim($rawLogo, '/');

                $candidates = [
                    public_path($rawLogo),
                    public_path('storage/' . $rawLogo),
                    storage_path('app/public/' . $rawLogo),
                    storage_path('app/' . $rawLogo),
                ];

                foreach ($candidates as $candidate) {
                    if (is_file($candidate)) {
                        $mime = mime_content_type($candidate) ?: 'image/png';
                        $logoSrc = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($candidate));
                        break 2;
                    }
                }
            }
        }

        $filename = 'salida-pdv-' . preg_replace('/[^A-Za-z0-9\-_]/', '-', (string) ($movement->reference ?? $orderRow->number ?? $orderRow->id)) . '.pdf';

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pos.inventory-output-pdf', [
                'order' => $orderRow,
                'metadata' => $metadata,
                'movement' => $movement,
                'movementLines' => $movementLines,
                'products' => $products,
                'company' => $company,
                'companyName' => $companyName,
                'logoSrc' => $logoSrc,
            ])
            ->setPaper('letter', 'portrait')
            ->stream($filename);
    }


    public function stockRefresh(\Illuminate\Http\Request $request, int $session)
    {
        abort_unless(auth()->check(), 403);

        $sessionRow = \Illuminate\Support\Facades\DB::table('pos_sessions')
            ->where('id', $session)
            ->first();

        abort_if(! $sessionRow, 404, 'Sesión PDV no encontrada.');

        $pos = $this->posPoint((int) $sessionRow->pos_point_id);
        $this->authorizePos($pos);

        $ids = collect($request->input('product_ids', []))
            ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return response()->json([
                'ok' => true,
                'stocks' => [],
            ]);
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('products')) {
            return response()->json([
                'ok' => false,
                'message' => 'No existe catálogo de productos.',
            ], 500);
        }

        $query = \Illuminate\Support\Facades\DB::table('products')
            ->whereIn('id', $ids);

        if (! empty($pos->company_id) && \Illuminate\Support\Facades\Schema::hasColumn('products', 'company_id')) {
            $query->where('company_id', $pos->company_id);
        }

        $products = $query->get()->keyBy('id');
        $stocks = [];

        foreach ($ids as $id) {
            $product = $products->get($id);

            if (! $product) {
                continue;
            }

            $stock = $this->stockForProduct($product, $pos);

            $productType = (string) ($product->product_type ?? 'stockable');
            $isService = $productType === 'service';

            $stocks[(string) $id] = [
                'product_id' => $id,
                'stock' => round((float) $stock, 6),
                'quantity' => round((float) $stock, 6),
                'product_type' => $productType,
                'is_service' => $isService,
            ];
        }

        return response()->json([
            'ok' => true,
            'stocks' => $stocks,
        ]);
    }


    protected function v5481jApplyPendingPaymentAdjustments(object $orderRow, \Illuminate\Http\Request $request): float
    {
        $orderId = (int) ($orderRow->id ?? 0);

        if ($orderId <= 0 || ! \Illuminate\Support\Facades\Schema::hasTable('pos_orders')) {
            return (float) ($orderRow->total ?? 0);
        }

        $requestedTotal = $request->input('total', null);
        $discountInput = $request->input('discount', null);

        // V5.51.7C - Backend permiso descuento PDV en actualización.
        if ($this->v5517cHasDiscountInput($discountInput) && ! $this->v5517cCanApplyPosDiscount()) {
            try {
                if (method_exists($this, 'v5515aWritePosAuditLog')) {
                    $this->v5515aWritePosAuditLog('pos.discount.blocked', [
                        'pos_order_id' => $orderId ?? null,
                        'entity_type' => 'pos_order',
                        'entity_id' => $orderId ?? null,
                        'description' => 'Intento bloqueado de aplicar descuento en actualización de ticket PDV.',
                        'after_data' => [
                            'discount' => $this->v5517cNormalizeDiscountInput($discountInput),
                        ],
                        'metadata' => [
                            'source' => 'v5517c_update_order_discount_guard',
                            'route' => optional($request->route())->getName(),
                            'url' => $request->fullUrl(),
                        ],
                    ]);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('No se pudo auditar descuento bloqueado actualización PDV.', [
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'ok' => false,
                'message' => 'No tienes permiso para aplicar descuentos.',
            ], 403);
        }


        if (is_string($discountInput) && trim($discountInput) !== '') {
            $decoded = json_decode($discountInput, true);
            $discountInput = is_array($decoded) ? $decoded : null;
        }

        $items = collect($request->input('items', []))
            ->filter(fn ($item) => is_array($item))
            ->values();

        /*
         * Si no llegó descuento ni total, dejamos el ticket como estaba.
         */
        if (! is_array($discountInput) && (! is_numeric($requestedTotal) || (float) $requestedTotal <= 0)) {
            return (float) ($orderRow->total ?? 0);
        }

        $grossTotal = 0.0;
        $grossSubtotal = 0.0;
        $grossTax = 0.0;

        if ($items->isNotEmpty()) {
            foreach ($items as $item) {
                $qty = (float) ($item['qty'] ?? $item['quantity'] ?? 0);
                $price = (float) ($item['price'] ?? $item['unit_price'] ?? 0);
                $taxRate = (float) ($item['tax_rate'] ?? 0.16);

                if ($taxRate > 1) {
                    $taxRate = $taxRate / 100;
                }

                if ($taxRate < 0) {
                    $taxRate = 0;
                }

                // BEXIA_V5820E2A1_PAY_ORDER_BLOCK_ZERO_PRICE
                if ($qty <= 0 || $price <= 0) {
                    continue;
                }

                $lineTotal = round($qty * $price, 4);
                $lineSubtotal = $taxRate > 0 ? round($lineTotal / (1 + $taxRate), 4) : $lineTotal;
                $lineTax = round($lineTotal - $lineSubtotal, 4);

                $grossTotal += $lineTotal;
                $grossSubtotal += $lineSubtotal;
                $grossTax += $lineTax;
            }
        }

        if ($grossTotal <= 0) {
            $grossTotal = (float) ($orderRow->total ?? 0);
            $grossSubtotal = (float) ($orderRow->subtotal ?? 0);
            $grossTax = (float) ($orderRow->tax_total ?? 0);

            if ($grossSubtotal <= 0 && $grossTotal > 0) {
                $grossSubtotal = round($grossTotal / 1.16, 4);
                $grossTax = round($grossTotal - $grossSubtotal, 4);
            }
        }

        $discountAmount = 0.0;
        $discountMetadata = null;

        if (is_array($discountInput)) {
            $type = (string) ($discountInput['type'] ?? 'amount');
            $type = $type === 'percent' ? 'percent' : 'amount';

            $value = (float) ($discountInput['value'] ?? 0);

            if ($value > 0) {
                if ($type === 'percent') {
                    $value = min($value, 100);
                    $discountAmount = round($grossTotal * ($value / 100), 4);
                } else {
                    $discountAmount = round($value, 4);
                }

                $discountAmount = max(0, min($discountAmount, $grossTotal));

                $discountMetadata = [
                    'type' => $type,
                    'value' => $value,
                    'amount' => $discountAmount,
                    'user_id' => $discountInput['user_id'] ?? auth()->id(),
                    'user_name' => $discountInput['user_name'] ?? (auth()->user()->name ?? auth()->user()->email ?? 'Usuario'),
                    'applied_at' => $discountInput['applied_at'] ?? now()->toISOString(),
                ];
            }
        }

        /*
         * El total que cobra el frontend es autoridad operativa del momento,
         * pero se valida contra el cálculo del descuento para evitar inconsistencias grandes.
         */
        $calculatedTotal = round(max(0, $grossTotal - $discountAmount), 4);

        if (is_numeric($requestedTotal) && (float) $requestedTotal > 0) {
            $requestedTotal = round((float) $requestedTotal, 4);

            if (abs($requestedTotal - $calculatedTotal) <= 0.05) {
                $calculatedTotal = $requestedTotal;
            }
        }

        $factor = $grossTotal > 0 ? ($calculatedTotal / $grossTotal) : 1;

        $newSubtotal = round($grossSubtotal * $factor, 4);
        $newTax = round($grossTax * $factor, 4);
        $newTotal = round($calculatedTotal, 4);

        $metadata = [];

        if (! empty($orderRow->metadata)) {
            $decoded = json_decode((string) $orderRow->metadata, true);
            $metadata = is_array($decoded) ? $decoded : [];
        }

        if ($discountMetadata) {
            $metadata['discount'] = $discountMetadata;


        } else {
            unset($metadata['discount']);
        }

        $metadata['payment_adjusted_at'] = now()->toDateTimeString();
        $metadata['payment_adjusted_by_user_id'] = auth()->id();

        $updates = [
            'subtotal' => $newSubtotal,
            'tax_total' => $newTax,
            'total' => $newTotal,
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => now(),
        ];

        $updates = array_intersect_key(
            $updates,
            array_flip(\Illuminate\Support\Facades\Schema::getColumnListing('pos_orders'))
        );

        \Illuminate\Support\Facades\DB::table('pos_orders')
            ->where('id', $orderId)
            ->update($updates);

        // V5.51.7E - Auditar descuento después de actualizar ticket con orderId real.
        $this->v5517dAuditDiscountFromSavedOrder(
            $orderId,
            'v5517e_discount_after_update_order'
        );


        /*
         * También actualizamos el objeto local para que el resto de payOrder
         * vea el total vigente.
         */
        $orderRow->subtotal = $newSubtotal;
        $orderRow->tax_total = $newTax;
        $orderRow->total = $newTotal;
        $orderRow->metadata = $updates['metadata'] ?? ($orderRow->metadata ?? null);

        return $newTotal;
    }


    protected function v5483PendingOrdersQuery(object $sessionRow, object $pos)
    {
        $query = \Illuminate\Support\Facades\DB::table('pos_orders as o')
            ->where('o.status', 'pending_payment');

        /*
         * Pendientes globales entre sesiones:
         * se muestran todos los pendientes del mismo PDV.
         * Si después se usan varios PDV por empresa, esto evita mezclar cajas distintas.
         */
        if (! empty($pos->id) && \Illuminate\Support\Facades\Schema::hasColumn('pos_orders', 'pos_point_id')) {
            $query->where('o.pos_point_id', (int) $pos->id);
        } elseif (! empty($pos->company_id) && \Illuminate\Support\Facades\Schema::hasColumn('pos_orders', 'company_id')) {
            $query->where('o.company_id', (int) $pos->company_id);
        }

        $selects = [
            'o.id',
            'o.number',
            'o.total',
            'o.status',
            'o.created_at',
            'o.updated_at',
            'o.pos_session_id',
            'o.pos_point_id',
            'o.company_id',
            'o.customer_id',
            \Illuminate\Support\Facades\DB::raw("'' as seller_name"),
            \Illuminate\Support\Facades\DB::raw("'' as customer_name"),
            \Illuminate\Support\Facades\DB::raw("'' as customer_rfc"),
            \Illuminate\Support\Facades\DB::raw("'' as customer_email"),
            \Illuminate\Support\Facades\DB::raw("'' as customer_phone"),
            \Illuminate\Support\Facades\DB::raw("'' as session_number"),
        ];

        if (
            \Illuminate\Support\Facades\Schema::hasTable('contacts')
            && \Illuminate\Support\Facades\Schema::hasColumn('pos_orders', 'customer_id')
        ) {
            $query->leftJoin('contacts as c', 'c.id', '=', 'o.customer_id');

            $contactColumns = \Illuminate\Support\Facades\Schema::getColumnListing('contacts');

            $nameExprs = [];

            foreach (['name', 'commercial_name', 'business_name', 'display_name', 'legal_name'] as $column) {
                if (in_array($column, $contactColumns, true)) {
                    $nameExprs[] = "c.{$column}";
                }
            }

            if ($nameExprs) {
                $selects[] = \Illuminate\Support\Facades\DB::raw('COALESCE(' . implode(', ', $nameExprs) . ", '') as customer_name");
            }

            if (in_array('rfc', $contactColumns, true)) {
                $selects[] = \Illuminate\Support\Facades\DB::raw("COALESCE(c.rfc, '') as customer_rfc");
            }

            if (in_array('email', $contactColumns, true)) {
                $selects[] = \Illuminate\Support\Facades\DB::raw("COALESCE(c.email, '') as customer_email");
            }

            if (in_array('phone', $contactColumns, true)) {
                $selects[] = \Illuminate\Support\Facades\DB::raw("COALESCE(c.phone, '') as customer_phone");
            }
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('pos_sessions')) {
            $query->leftJoin('pos_sessions as ps', 'ps.id', '=', 'o.pos_session_id');

            if (\Illuminate\Support\Facades\Schema::hasColumn('pos_sessions', 'number')) {
                $selects[] = \Illuminate\Support\Facades\DB::raw("COALESCE(ps.number, '') as session_number");
            }
        }

        if (
            \Illuminate\Support\Facades\Schema::hasTable('employees')
            && \Illuminate\Support\Facades\Schema::hasColumn('pos_orders', 'employee_id')
        ) {
            $query->leftJoin('employees as e', 'e.id', '=', 'o.employee_id');

            $employeeColumns = \Illuminate\Support\Facades\Schema::getColumnListing('employees');
            $employeeNameExprs = [];

            foreach (['name', 'full_name', 'display_name'] as $column) {
                if (in_array($column, $employeeColumns, true)) {
                    $employeeNameExprs[] = "e.{$column}";
                }
            }

            if ($employeeNameExprs) {
                $selects[] = \Illuminate\Support\Facades\DB::raw('COALESCE(' . implode(', ', $employeeNameExprs) . ", '') as seller_name");
            }
        }

        return $query->select($selects);
    }

    protected function v5483PendingOrderPayload(object $row, object $currentSession): array
    {
        $isCurrentSession = (int) ($row->pos_session_id ?? 0) === (int) ($currentSession->id ?? 0);

        return [
            'id' => (int) $row->id,
            'number' => (string) ($row->number ?? ''),
            'total' => (float) ($row->total ?? 0),
            'status' => (string) ($row->status ?? 'pending_payment'),
            'status_label' => 'Pendiente de cobro',
            'pos_session_id' => $row->pos_session_id ? (int) $row->pos_session_id : null,
            'session_number' => (string) ($row->session_number ?? ''),
            'pending_scope' => $isCurrentSession ? 'current' : 'previous',
            'pending_scope_label' => $isCurrentSession ? 'Sesión actual' : 'Sesión anterior',
            'seller_name' => (string) ($row->seller_name ?? ''),
            'customer_id' => $row->customer_id ? (int) $row->customer_id : null,
            'customer_name' => (string) ($row->customer_name ?? ''),
            'customer_rfc' => (string) ($row->customer_rfc ?? ''),
            'customer_email' => (string) ($row->customer_email ?? ''),
            'customer_phone' => (string) ($row->customer_phone ?? ''),
            'created_at' => (string) ($row->created_at ?? ''),
            'updated_at' => (string) ($row->updated_at ?? ''),
        ];
    }


    public function closeSessionSummary(\Illuminate\Http\Request $request, int $session)
    {
        abort_unless(auth()->check(), 403);

        $summary = $this->v5484BuildCloseSessionSummary($session);

        return response()->json([
            'ok' => true,
            'summary' => $summary,
        ]);
    }

    public function sessionSalesReport(\Illuminate\Http\Request $request, int $session)
    {
        abort_unless(auth()->check(), 403);

        $sessionRow = \Illuminate\Support\Facades\DB::table('pos_sessions')
            ->where('id', $session)
            ->first();

        abort_if(! $sessionRow, 404, 'Sesión PDV no encontrada.');

        $pos = $this->posPoint((int) $sessionRow->pos_point_id);
        $this->authorizePos($pos);

        $summary = $this->v5484BuildCloseSessionSummary($session);

        $v5521eUrlFormatKey = strtolower(trim((string) $request->query('format', $request->query('close_format', ''))));
        $v5521eConfiguredFormatKey = strtolower(trim((string) ($pos->session_close_format ?? '')));
        $v5521eFormatKey = $v5521eUrlFormatKey !== '' ? $v5521eUrlFormatKey : $v5521eConfiguredFormatKey;
        $v5521eCloseFormat = in_array($v5521eFormatKey, ['papelon', 'papelón'], true) ? 'papelon' : 'generic';
        $v5521eView = 'pos.session-sales-report';

        if ($v5521eCloseFormat === 'papelon') {
            $summary['papelon_close'] = \App\Support\PosPapelonCloseSummary::build($session);

            if (view()->exists('pos.session-sales-report-papelon')) {
                $v5521eView = 'pos.session-sales-report-papelon';
            }
        }

        $v5521eLogoUrl = $summary['company']['logo_url'] ?? null;

        if (! $v5521eLogoUrl && method_exists($this, 'ticketLogoUrl')) {
            $v5521eLogoUrl = $this->ticketLogoUrl($pos);
        }

        if (! $v5521eLogoUrl && method_exists($this, 'v5521c4ResolveCompanyLogoUrl')) {
            $v5521eLogoUrl = $this->v5521c4ResolveCompanyLogoUrl($pos, $sessionRow);
        }

        $html = view($v5521eView, [
            'summary' => $summary,
            'session' => $sessionRow,
            'closeFormat' => $v5521eCloseFormat,
            'companyLogoUrl' => $v5521eLogoUrl,
        ])->render();

        $filename = 'reporte-venta-' . preg_replace('/[^A-Za-z0-9_\-]/', '-', (string) ($sessionRow->number ?? ('sesion-' . $session))) . '.pdf';

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
                ->setPaper('letter', 'portrait');

            /*
             * Renderizamos primero para que DomPDF conozca el total de páginas.
             * Luego agregamos el footer con canvas/page_text.
             */
            $dompdf = $pdf->getDomPDF();
            $dompdf->render();

            $canvas = $dompdf->getCanvas();
            $fontMetrics = $dompdf->getFontMetrics();

            $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
            $size = 8;
            $color = [0.35, 0.35, 0.35];

            $width = $canvas->get_width();
            $height = $canvas->get_height();

            $leftX = 36;
            $rightMargin = 36;
            $y = $height - 24;

            $sessionText = 'Sesión: ' . (string) ($sessionRow->number ?? ('#' . $sessionRow->id));
            $pageText = 'Página {PAGE_NUM} de {PAGE_COUNT}';

            $pageTextWidth = $fontMetrics->getTextWidth('Página 999 de 999', $font, $size);
            $rightX = max(260, $width - $rightMargin - $pageTextWidth);

            /*
             * Línea separadora del pie.
             */
            $canvas->line($leftX, $y - 7, $width - $rightMargin, $y - 7, [0.82, 0.86, 0.91], 0.5);

            $canvas->page_text($leftX, $y, $sessionText, $font, $size, $color);
            $canvas->page_text($rightX, $y, $pageText, $font, $size, $color);

            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
            ]);
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }





    
protected function v5484BuildCloseSessionSummary(int $sessionId): array
    {
        abort_unless(\Illuminate\Support\Facades\Schema::hasTable('pos_sessions'), 500, 'No existe tabla pos_sessions.');

        $session = \Illuminate\Support\Facades\DB::table('pos_sessions')
            ->where('id', $sessionId)
            ->first();

        abort_if(! $session, 404, 'Sesión PDV no encontrada.');

        $pos = $this->posPoint((int) $session->pos_point_id);
        $this->authorizePos($pos);

        $openedAt = $session->opened_at ?? $session->created_at ?? null;

        $companyId = (int) ($session->company_id ?? 0);
        $posPointId = (int) ($session->pos_point_id ?? 0);

        $ordersBase = \Illuminate\Support\Facades\DB::table('pos_orders')
            ->where('pos_point_id', $posPointId);

        if ($companyId > 0 && \Illuminate\Support\Facades\Schema::hasColumn('pos_orders', 'company_id')) {
            $ordersBase->where('company_id', $companyId);
        }

        $createdInSession = (clone $ordersBase)
            ->where('pos_session_id', $sessionId);

        $createdTickets = (int) (clone $createdInSession)->count();
        $createdPendingTickets = (int) (clone $createdInSession)->where('status', 'pending_payment')->count();
        $createdPendingTotal = round((float) (clone $createdInSession)->where('status', 'pending_payment')->sum('total'), 2);

        $paymentsTableExists = \Illuminate\Support\Facades\Schema::hasTable('pos_order_payments');

        $paidOrderIds = collect();
        $paymentsByMethod = [];
        $paymentsTotal = 0.0;
        $paymentsRows = collect();

        if ($paymentsTableExists) {
            $paymentColumns = \Illuminate\Support\Facades\Schema::getColumnListing('pos_order_payments');

            $paymentsQuery = \Illuminate\Support\Facades\DB::table('pos_order_payments as p')
                ->join('pos_orders as o', 'o.id', '=', 'p.pos_order_id')
                ->where('o.pos_point_id', $posPointId);

            if ($companyId > 0 && \Illuminate\Support\Facades\Schema::hasColumn('pos_orders', 'company_id')) {
                $paymentsQuery->where('o.company_id', $companyId);
            }

            /*
             * Regla operativa:
             * - Si pos_order_payments tiene pos_session_id, el corte usa esa sesión de cobro.
             * - Si no existe todavía, usa ventana de tiempo desde apertura.
             *
             * Esto permite que en el futuro, con caja vendedora y caja cobradora,
             * el dinero salga en la caja donde se cobró.
             */
            if (in_array('pos_session_id', $paymentColumns, true)) {
                $paymentsQuery->where('p.pos_session_id', $sessionId);
            } else {
                if ($openedAt) {
                    $paymentsQuery->where('p.created_at', '>=', $openedAt);
                }

                if (($session->status ?? null) === 'closed' && ! empty($session->closed_at)) {
                    $paymentsQuery->where('p.created_at', '<=', $session->closed_at);
                }
            }

            $paymentsRows = (clone $paymentsQuery)
                ->get([
                    'p.id',
                    'p.pos_order_id',
                    'p.amount',
                    'p.payment_label',
                    'p.created_at',
                    'o.number as order_number',
                    'o.total as order_total',
                    'o.pos_session_id as order_session_id',
                    'o.employee_id as order_employee_id',
                    'o.created_at as order_created_at',
                ]);

            $paidOrderIds = $paymentsRows
                ->pluck('pos_order_id')
                ->filter()
                ->unique()
                ->values();

            $paymentsByMethod = $paymentsRows
                ->groupBy(fn ($row) => trim((string) ($row->payment_label ?? '')) !== '' ? trim((string) $row->payment_label) : 'Sin método')
                ->map(fn ($rows, $method) => [
                    'method' => (string) $method,
                    'payments_count' => $rows->count(),
                    'total' => round((float) $rows->sum('amount'), 2),
                ])
                ->sortBy('method')
                ->values()
                ->all();

            $paymentsTotal = round((float) $paymentsRows->sum('amount'), 2);
        } else {
            $paidOrdersQuery = (clone $ordersBase)
                ->where('status', 'paid')
                ->where('pos_session_id', $sessionId);

            $paidOrderIds = (clone $paidOrdersQuery)
                ->pluck('id')
                ->filter()
                ->unique()
                ->values();

            $paymentsTotal = round((float) (clone $paidOrdersQuery)->sum('total'), 2);

            $paymentsByMethod = [
                [
                    'method' => 'Total cobrado',
                    'payments_count' => $paidOrderIds->count(),
                    'total' => $paymentsTotal,
                ],
            ];
        }

        $paidTickets = $paidOrderIds->count();

        $paidOrdersCollection = $paidOrderIds->isNotEmpty()
            ? \Illuminate\Support\Facades\DB::table('pos_orders')
                ->whereIn('id', $paidOrderIds)
                ->orderBy('number')
                ->get(array_values(array_filter([
                    'id',
                    'number',
                    'total',
                    'status',
                    'pos_session_id',
                    'employee_id',
                    \Illuminate\Support\Facades\Schema::hasColumn('pos_orders', 'customer_id') ? 'customer_id' : null,
                    \Illuminate\Support\Facades\Schema::hasColumn('pos_orders', 'price_list_id') ? 'price_list_id' : null,
                    \Illuminate\Support\Facades\Schema::hasColumn('pos_orders', 'price_list_name') ? 'price_list_name' : null,
                    \Illuminate\Support\Facades\Schema::hasColumn('pos_orders', 'metadata') ? 'metadata' : null,
                    'created_at',
                    'updated_at',
                ])))
            : collect();

        $sessionEmployeeNames = collect();

        if (\Illuminate\Support\Facades\Schema::hasTable('pos_sessions') && \Illuminate\Support\Facades\Schema::hasTable('employees')) {
            $sessionEmployeeIds = $paidOrdersCollection
                ->pluck('pos_session_id')
                ->filter()
                ->unique()
                ->values();

            if ($sessionEmployeeIds->isNotEmpty()) {
                $sessionEmployees = \Illuminate\Support\Facades\DB::table('pos_sessions as ps')
                    ->leftJoin('employees as e', 'e.id', '=', 'ps.employee_id')
                    ->whereIn('ps.id', $sessionEmployeeIds)
                    ->get([
                        'ps.id as session_id',
                        'ps.employee_id',
                        'e.name',
                    ]);

                foreach ($sessionEmployees as $row) {
                    $sessionEmployeeNames[(int) $row->session_id] = $row->name ?: ('Empleado #' . ($row->employee_id ?? ''));
                }
            }
        }

        $employeeNames = collect();

        if (\Illuminate\Support\Facades\Schema::hasTable('employees')) {
            $employeeIds = $paidOrdersCollection
                ->pluck('employee_id')
                ->filter()
                ->unique()
                ->values();

            if ($employeeIds->isNotEmpty()) {
                $employeeRows = \Illuminate\Support\Facades\DB::table('employees')
                    ->whereIn('id', $employeeIds)
                    ->get(['id', 'name']);

                foreach ($employeeRows as $row) {
                    $employeeNames[(int) $row->id] = $row->name ?: ('Empleado #' . $row->id);
                }
            }
        }

        $paymentsByOrder = $paymentsRows
            ->groupBy('pos_order_id')
            ->map(function ($rows) {
                return $rows
                    ->groupBy(fn ($row) => trim((string) ($row->payment_label ?? '')) !== '' ? trim((string) $row->payment_label) : 'Sin método')
                    ->map(fn ($methodRows, $method) => [
                        'method' => (string) $method,
                        'total' => round((float) $methodRows->sum('amount'), 2),
                        'payments_count' => $methodRows->count(),
                    ])
                    ->values()
                    ->all();
            });

        $paidOrders = $paidOrdersCollection
            ->map(function ($row) use ($sessionId, $employeeNames, $sessionEmployeeNames, $paymentsByOrder) {
                $seller = '';

                if (! empty($row->employee_id) && isset($employeeNames[(int) $row->employee_id])) {
                    $seller = $employeeNames[(int) $row->employee_id];
                }

                if ($seller === '' && ! empty($row->pos_session_id) && isset($sessionEmployeeNames[(int) $row->pos_session_id])) {
                    $seller = $sessionEmployeeNames[(int) $row->pos_session_id];
                }

                if ($seller === '') {
                    $seller = 'Sin vendedor';
                }

                return [
                    'id' => (int) $row->id,
                    'number' => (string) $row->number,
                    'total' => round((float) $row->total, 2),
                    'status' => (string) $row->status,
                    'created_in_current_session' => (int) ($row->pos_session_id ?? 0) === $sessionId,
                    'seller_name' => $seller,
                    'price_list_id' => isset($row->price_list_id) && is_numeric($row->price_list_id) ? (int) $row->price_list_id : null,
                    'price_list_name' => (string) ($row->price_list_name ?? ''),
                    'metadata' => (string) ($row->metadata ?? ''),
                    'payments' => $paymentsByOrder->get($row->id, []),
                    'created_at' => (string) ($row->created_at ?? ''),
                    'updated_at' => (string) ($row->updated_at ?? ''),
                ];
            })
            ->values();

        $salesBySeller = $paidOrders
            ->groupBy('seller_name')
            ->map(fn ($rows, $seller) => [
                'seller_name' => (string) $seller,
                'tickets_count' => $rows->count(),
                'total' => round((float) $rows->sum('total'), 2),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();

        $activeReservations = 0;
        $activeReservedQty = 0.0;

        if (\Illuminate\Support\Facades\Schema::hasTable('stock_reservations')) {
            $reservationQuery = \Illuminate\Support\Facades\DB::table('stock_reservations as r')
                ->join('pos_orders as o', 'o.id', '=', 'r.source_id')
                ->where('r.source_type', 'pos_order')
                ->where('r.status', 'active')
                ->where('o.status', 'pending_payment')
                ->where('o.pos_point_id', $posPointId);

            if ($companyId > 0 && \Illuminate\Support\Facades\Schema::hasColumn('pos_orders', 'company_id')) {
                $reservationQuery->where('o.company_id', $companyId);
            }

            $activeReservations = (int) (clone $reservationQuery)->count();
            $activeReservedQty = round((float) (clone $reservationQuery)->sum('r.quantity'), 6);
        }


        $cashMovements = collect();
        $cashInTotal = 0.0;
        $cashOutTotal = 0.0;

        if (\Illuminate\Support\Facades\Schema::hasTable('pos_cash_movements')) {
            $cashMovements = \Illuminate\Support\Facades\DB::table('pos_cash_movements')
                ->where('pos_session_id', $sessionId)
                ->orderBy('id')
                ->get();

            $cashInTotal = round((float) $cashMovements->where('type', 'cash_in')->sum('amount'), 2);
            $cashOutTotal = round((float) $cashMovements->where('type', 'cash_out')->sum('amount'), 2);
        }

        $cashPaymentsTotal = 0.0;

        foreach ($paymentsByMethod as $methodRow) {
            $methodName = mb_strtolower((string) ($methodRow['method'] ?? ''));

            if (
                str_contains($methodName, 'efectivo')
                || str_contains($methodName, 'cash')
            ) {
                $cashPaymentsTotal += (float) ($methodRow['total'] ?? 0);
            }
        }
        $openingCashAmount = round((float) ($session->opening_amount ?? 0), 2);

        $expectedCash = round(
            $openingCashAmount
            + $cashPaymentsTotal
            + $cashInTotal
            - $cashOutTotal,
            2
        );

        $denominations = [];

        try {
            if (method_exists($this, 'cashDenominationsForPos')) {
                $denominations = $this->cashDenominationsForPos($pos);
            }
        } catch (\Throwable $e) {
            $denominations = [];
        }

        if (! $denominations) {
            $denominations = [
                ['id' => null, 'name' => '$0.50', 'value' => 0.50, 'type' => 'coin'],
                ['id' => null, 'name' => '$1.00', 'value' => 1.00, 'type' => 'coin'],
                ['id' => null, 'name' => '$2.00', 'value' => 2.00, 'type' => 'coin'],
                ['id' => null, 'name' => '$5.00', 'value' => 5.00, 'type' => 'coin'],
                ['id' => null, 'name' => '$10.00', 'value' => 10.00, 'type' => 'coin'],
                ['id' => null, 'name' => '$20.00', 'value' => 20.00, 'type' => 'bill'],
                ['id' => null, 'name' => '$50.00', 'value' => 50.00, 'type' => 'bill'],
                ['id' => null, 'name' => '$100.00', 'value' => 100.00, 'type' => 'bill'],
                ['id' => null, 'name' => '$200.00', 'value' => 200.00, 'type' => 'bill'],
                ['id' => null, 'name' => '$500.00', 'value' => 500.00, 'type' => 'bill'],
                ['id' => null, 'name' => '$1000.00', 'value' => 1000.00, 'type' => 'bill'],
            ];
        }

        $cashierName = '';

        if (! empty($session->employee_id) && \Illuminate\Support\Facades\Schema::hasTable('employees')) {
            $employee = \Illuminate\Support\Facades\DB::table('employees')->where('id', $session->employee_id)->first();

            if ($employee) {
                $cashierName = $employee->name ?? $employee->full_name ?? $employee->display_name ?? ('Empleado #' . $employee->id);
            }
        }

        if ($cashierName === '' && ! empty($session->opened_by_user_id) && \Illuminate\Support\Facades\Schema::hasTable('users')) {
            $user = \Illuminate\Support\Facades\DB::table('users')->where('id', $session->opened_by_user_id)->first();

            if ($user) {
                $cashierName = $user->name ?? $user->email ?? ('Usuario #' . $user->id);
            }
        }

        $posName = $pos->name
            ?? $pos->display_name
            ?? $pos->code
            ?? ('PDV #' . ($pos->id ?? ''));

        $openingCashPayload = $this->v5487OpeningCashPayloadFromSessionNotes($session->notes ?? null);
        $openingCashCount = collect($openingCashPayload['cash_count'] ?? [])
            ->filter(fn ($row) => is_array($row))
            ->values()
            ->all();

        $companyName = $this->currentCompanyLabel((int) ($session->company_id ?? 0));

        $logoUrl = null;

        try {
            if (method_exists($this, 'ticketLogoUrl')) {
                $logoUrl = $this->ticketLogoUrl($pos);
            }
        } catch (\Throwable $e) {
            $logoUrl = null;
        }

        return [
            'session' => [
                'id' => (int) $session->id,
                'number' => (string) ($session->number ?? ('Sesión #' . $session->id)),
                'status' => (string) ($session->status ?? ''),
                'status_label' => match ((string) ($session->status ?? '')) {
                    'open' => 'Abierto',
                    'closed' => 'Cerrado',
                    'cancelled' => 'Cancelado',
                    default => ucfirst((string) ($session->status ?? '')),
                },
                'opened_at' => (string) ($session->opened_at ?? ''),
                'closed_at' => (string) ($session->closed_at ?? ''),
                'opening_amount' => round((float) ($session->opening_amount ?? 0), 2),
                'closing_amount' => round((float) ($session->closing_amount ?? 0), 2),
                'closing_difference' => round((float) ($closePayload['closing_difference'] ?? 0), 2),
                'closing_note' => (string) ($closePayload['closing_note'] ?? ''),
                'closing_cash_count' => is_array($closePayload['cash_count'] ?? null) ? $closePayload['cash_count'] : [],
                'staff_role' => (string) ($session->staff_role ?? ''),
            ],
            'company' => [
                'id' => (int) ($session->company_id ?? 0),
                'name' => $companyName,
                'logo_url' => $logoUrl,
            ],
            'pos' => [
                'id' => (int) ($pos->id ?? 0),
                'name' => (string) $posName,
                'code' => (string) ($pos->code ?? ''),
            ],
            'cashier' => [
                'name' => $cashierName !== '' ? $cashierName : 'Sin cajero',
            ],
            'opening_cash' => [
                'amount' => round((float) ($session->opening_amount ?? ($openingCashPayload['opening_amount'] ?? 0)), 2),
                'cash_count' => $openingCashCount,
                'opened_by_name' => (string) ($openingCashPayload['opened_by_name'] ?? ''),
            ],
            'totals' => [
                'created_tickets' => $createdTickets,
                'paid_tickets' => $paidTickets,
                'paid_total' => $paymentsTotal,
                'pending_tickets_created_in_session' => $createdPendingTickets,
                'pending_total_created_in_session' => $createdPendingTotal,
                'active_reservations' => $activeReservations,
                'active_reserved_quantity' => $activeReservedQty,
                'opening_cash_amount' => $openingCashAmount,
                'cash_payments_total' => round($cashPaymentsTotal, 2),
                'cash_in_total' => $cashInTotal,
                'cash_out_total' => $cashOutTotal,
                'expected_cash' => $expectedCash,
            ],
            'cash_movements' => $cashMovements
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'number' => (string) ($row->number ?? ''),
                    'type' => (string) ($row->type ?? ''),
                    'type_label' => (string) ($row->type ?? '') === 'cash_in' ? 'Entrada' : 'Retiro',
                    'amount' => round((float) ($row->amount ?? 0), 2),
                    'reason' => (string) ($row->reason ?? ''),
                    'performed_by_name' => (string) ($row->performed_by_name ?? ''),
                    'supervisor_name' => (string) ($row->supervisor_name ?? ''),
                    'movement_at' => (string) ($row->movement_at ?? ''),
                    'print_url' => url('/pos/cash-movements/' . $row->id . '/print'),
                ])
                ->values()
                ->all(),
            'denominations' => collect($denominations)
                ->map(fn ($row) => [
                    'id' => $row['id'] ?? null,
                    'name' => (string) ($row['name'] ?? ('$' . number_format((float) ($row['value'] ?? 0), 2))),
                    'value' => round((float) ($row['value'] ?? 0), 2),
                    'type' => (string) ($row['type'] ?? ''),
                ])
                ->filter(fn ($row) => $row['value'] > 0)
                /*
                 * Evita duplicados en el modal de cierre.
                 * Si la configuración trae dos veces Billete de 1000, Moneda de 10, etc.,
                 * mostramos una sola línea por valor.
                 */
                /*
                 * Importante:
                 * No deduplicar solo por valor, porque en MXN existen casos válidos como:
                 * - Moneda de 20
                 * - Billete de 20
                 *
                 * Debe deduplicar por tipo + valor.
                 */
                ->unique(fn ($row) => mb_strtolower((string) ($row['type'] ?? '')) . '|' . number_format((float) $row['value'], 2, '.', ''))
                ->sortByDesc(fn ($row) => (float) $row['value'])
                ->values()
                ->all(),
            'payments_by_method' => $paymentsByMethod,
            'sales_by_seller' => $salesBySeller,
            'paid_orders' => $paidOrders->all(),
            'generated_at' => now()->toDateTimeString(),
        ];
    }




    private function v5690d5PostCashPaymentToTreasury(object $orderRow, int $paymentId, array $payment): array
    {
        if ($paymentId <= 0) {
            return ['ok' => false, 'message' => 'No se pudo identificar el pago POS.'];
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('treasury_accounts') || ! \Illuminate\Support\Facades\Schema::hasTable('treasury_movements')) {
            return ['ok' => false, 'message' => 'No existen tablas de Tesoreria para registrar el efectivo.'];
        }

        $companyId = (int) ($orderRow->company_id ?? 0);
        $posPointId = (int) ($orderRow->pos_point_id ?? 0);
        $posSessionId = ! empty($orderRow->pos_session_id) ? (int) $orderRow->pos_session_id : null;
        $amount = round((float) ($payment['amount'] ?? 0), 2);

        if ($companyId <= 0) {
            return ['ok' => false, 'message' => 'El ticket POS no tiene empresa valida para Tesoreria.'];
        }

        if ($posPointId <= 0) {
            return ['ok' => false, 'message' => 'El ticket POS no tiene punto de venta valido para Tesoreria.'];
        }

        if ($amount <= 0) {
            return ['ok' => false, 'message' => 'El monto de efectivo debe ser mayor a cero.'];
        }

        $pos = null;
        $warehouseId = null;
        $branchId = null;

        if (\Illuminate\Support\Facades\Schema::hasTable('pos_points')) {
            $pos = \Illuminate\Support\Facades\DB::table('pos_points')
                ->where('id', $posPointId)
                ->first();

            if ($pos && ! empty($pos->warehouse_id)) {
                $warehouseId = (int) $pos->warehouse_id;
            }
        }

        if ($warehouseId && \Illuminate\Support\Facades\Schema::hasTable('warehouses')) {
            $warehouse = \Illuminate\Support\Facades\DB::table('warehouses')
                ->where('id', $warehouseId)
                ->first();

            if ($warehouse && ! empty($warehouse->branch_id)) {
                $branchId = (int) $warehouse->branch_id;
            }
        }

        $account = \Illuminate\Support\Facades\DB::table('treasury_accounts')
            ->where('company_id', $companyId)
            ->where('pos_point_id', $posPointId)
            ->where('cash_scope', 'pdv')
            ->where('is_active', true)
            ->lockForUpdate()
            ->first();

        if (! $account) {
            return [
                'ok' => false,
                'message' => 'No existe una Caja PDV activa ligada a este punto de venta. Configura Tesoreria > Cuentas / Cajas.',
            ];
        }

        $now = now();
        $paymentFormId = $payment['payment_form_id'] ?? null;
        $paymentFormId = $paymentFormId !== null && $paymentFormId !== '' ? (int) $paymentFormId : null;

        $movement = [
            'company_id' => $companyId,
            'treasury_account_id' => (int) $account->id,
            'payment_form_id' => $paymentFormId,
            'type' => 'inbound',
            'source_type' => 'pos_order_payment',
            'source_id' => $paymentId,
            'movement_date' => $now->toDateString(),
            'amount' => $amount,
            'currency_code' => $account->currency_code ?: ($orderRow->currency_code ?? 'MXN'),
            'reference' => (string) ($orderRow->number ?? ('POS-' . $orderRow->id)),
            'description' => 'Cobro efectivo POS ' . (string) ($orderRow->number ?? ('#' . $orderRow->id)),
            'status' => 'posted',
            'posted_at' => $now,
            'created_by_user_id' => auth()->id(),
            'metadata' => json_encode([
                'source' => 'pos_cash_payment',
                'pos_order_id' => (int) $orderRow->id,
                'pos_order_number' => (string) ($orderRow->number ?? ''),
                'pos_order_payment_id' => $paymentId,
                'pos_session_id' => $posSessionId,
                'pos_point_id' => $posPointId,
                'payment_label' => $payment['payment_label'] ?? null,
                'payment_form_code' => $payment['payment_form_code'] ?? null,
                'cash_received' => $payment['cash_received'] ?? null,
                'change_amount' => $payment['change_amount'] ?? 0,
                'amount_applied_to_sale' => $amount,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        foreach ([
            'pos_order_payment_id' => $paymentId,
            'pos_session_id' => $posSessionId,
            'pos_point_id' => $posPointId,
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
        ] as $column => $value) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('treasury_movements', $column)) {
                $movement[$column] = $value;
            }
        }

        $movementId = \Illuminate\Support\Facades\DB::table('treasury_movements')->insertGetId($movement);

        \Illuminate\Support\Facades\DB::table('treasury_accounts')
            ->where('id', (int) $account->id)
            ->increment('current_balance', $amount, [
                'updated_at' => $now,
            ]);

        $paymentUpdate = [
            'updated_at' => $now,
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('pos_order_payments', 'treasury_movement_id')) {
            $paymentUpdate['treasury_movement_id'] = $movementId;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('pos_order_payments', 'treasury_account_id')) {
            $paymentUpdate['treasury_account_id'] = (int) $account->id;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('pos_order_payments', 'treasury_posted_at')) {
            $paymentUpdate['treasury_posted_at'] = $now;
        }

        \Illuminate\Support\Facades\DB::table('pos_order_payments')
            ->where('id', $paymentId)
            ->update($paymentUpdate);

        return [
            'ok' => true,
            'treasury_account_id' => (int) $account->id,
            'treasury_movement_id' => (int) $movementId,
            'treasury_posted_at' => $now->toDateTimeString(),
        ];
    }


    public function storeCashMovement(\Illuminate\Http\Request $request, int $session)
    {
        abort_unless(auth()->check(), 403);

        abort_unless(\Illuminate\Support\Facades\Schema::hasTable('pos_cash_movements'), 500, 'Falta ejecutar migración de movimientos de efectivo.');

        $sessionRow = \Illuminate\Support\Facades\DB::table('pos_sessions')
            ->where('id', $session)
            ->first();

        abort_if(! $sessionRow, 404, 'Sesión PDV no encontrada.');

        if (($sessionRow->status ?? null) !== 'open') {
            return response()->json([
                'ok' => false,
                'message' => 'La sesión de PDV no está abierta.',
            ], 422);
        }

        $pos = $this->posPoint((int) $sessionRow->pos_point_id);
        $this->authorizePos($pos);

        $type = (string) $request->input('type', '');
        $type = in_array($type, ['cash_in', 'cash_out'], true) ? $type : '';

        if ($type === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Tipo de movimiento inválido.',
            ], 422);
        }

        $amount = round((float) $request->input('amount', 0), 2);

        if ($amount <= 0) {
            return response()->json([
                'ok' => false,
                'message' => 'El importe debe ser mayor a cero.',
            ], 422);
        }

        $reason = trim((string) $request->input('reason', ''));
        $notes = trim((string) $request->input('notes', ''));

        $supervisorEmployeeId = $request->input('supervisor_employee_id');
        $supervisorEmployeeId = is_numeric($supervisorEmployeeId) && (int) $supervisorEmployeeId > 0
            ? (int) $supervisorEmployeeId
            : null;

        $supervisorName = trim((string) $request->input('supervisor_name', ''));

        if ($supervisorEmployeeId && \Illuminate\Support\Facades\Schema::hasTable('employees')) {
            $supervisor = \Illuminate\Support\Facades\DB::table('employees')
                ->where('id', $supervisorEmployeeId)
                ->first();

            if ($supervisor) {
                $supervisorName = trim((string) (
                    $supervisor->name
                    ?? $supervisor->full_name
                    ?? $supervisor->display_name
                    ?? ('Empleado #' . $supervisor->id)
                ));
            }
        }

        if ($reason === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Indica el motivo del movimiento.',
            ], 422);
        }

        if ($supervisorName === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Indica el nombre del supervisor.',
            ], 422);
        }

        $prefix = $type === 'cash_in' ? 'ENT' : 'RET';

        $numberPrefix = $prefix . '-' . now()->format('Ymd') . '-';

        $lastNumber = \Illuminate\Support\Facades\DB::table('pos_cash_movements')
            ->where('number', 'like', $numberPrefix . '%')
            ->orderByDesc('number')
            ->value('number');

        $next = 1;

        if ($lastNumber && preg_match('/-(\d+)$/', (string) $lastNumber, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        $number = $numberPrefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);

        $user = auth()->user();

        $treasuryTransferRequestId = null;
        $treasuryStatus = 'not_linked';
        $treasuryMessage = null;
        $destinationTreasuryAccountId = null;

        try {
            $movementId = \Illuminate\Support\Facades\DB::transaction(function () use (
                $sessionRow,
                $pos,
                $type,
                $amount,
                $reason,
                $notes,
                $supervisorEmployeeId,
                $supervisorName,
                $number,
                $user,
                &$treasuryTransferRequestId,
                &$treasuryStatus,
                &$treasuryMessage,
                &$destinationTreasuryAccountId
            ) {
                $movementId = \Illuminate\Support\Facades\DB::table('pos_cash_movements')->insertGetId([
                    'company_id' => $sessionRow->company_id ?? null,
                    'pos_point_id' => $sessionRow->pos_point_id ?? null,
                    'pos_session_id' => $sessionRow->id,
                    'number' => $number,
                    'type' => $type,
                    'amount' => $amount,
                    'reason' => $reason,
                    'notes' => $notes ?: null,
                    'performed_by_user_id' => auth()->id(),
                    'performed_by_name' => $user->name ?? $user->email ?? ('Usuario #' . auth()->id()),
                    'supervisor_name' => $supervisorName,
                    'movement_at' => now(),
                    'metadata' => json_encode([
                        'source' => 'pos_close_cash_control',
                        'supervisor_employee_id' => $supervisorEmployeeId,
                        'requires_cashier_signature' => true,
                        'requires_supervisor_signature' => true,
                        'print_copies' => 2,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                /*
                 * V5.69.0d3:
                 * Solo los retiros de PDV generan solicitud formal en Tesoreria.
                 * Entradas de efectivo siguen registrandose como movimiento POS por ahora.
                 */
                if ($type === 'cash_out') {
                    $context = $this->v5690d3TreasuryCashOutContext($sessionRow, $pos);

                    if (! empty($context['error'])) {
                        throw new \RuntimeException($context['error']);
                    }

                    $sourceAccount = $context['source_account'];
                    $destinationAccount = $context['destination_account'];

                    $transferRequest = app(\App\Support\Treasury\CashTransferService::class)->createRequest([
                        'company_id' => (int) ($sessionRow->company_id ?? $pos->company_id ?? 0),
                        'branch_id' => $context['branch_id'] ?? null,
                        'warehouse_id' => $context['warehouse_id'] ?? null,
                        'pos_point_id' => (int) ($sessionRow->pos_point_id ?? $pos->id ?? 0),
                        'pos_session_id' => (int) $sessionRow->id,
                        'pos_cash_movement_id' => $movementId,
                        'source_treasury_account_id' => (int) $sourceAccount->id,
                        'destination_treasury_account_id' => (int) $destinationAccount->id,
                        'type' => 'pos_withdrawal',
                        'status' => 'requested',
                        'amount' => $amount,
                        'currency_code' => $sourceAccount->currency_code ?: 'MXN',
                        'reason' => 'Retiro PDV ' . $number . ': ' . $reason,
                        'notes' => $notes ?: null,
                        'requested_by_user_id' => auth()->id(),
                        'metadata' => [
                            'source' => 'pos_cash_movement',
                            'pos_cash_movement_id' => $movementId,
                            'pos_cash_movement_number' => $number,
                            'supervisor_name' => $supervisorName,
                            'supervisor_employee_id' => $supervisorEmployeeId,
                            'source_account_scope' => $sourceAccount->cash_scope ?? null,
                            'destination_account_scope' => $destinationAccount->cash_scope ?? null,
                        ],
                    ]);

                    $treasuryTransferRequestId = (int) $transferRequest->id;
                    $destinationTreasuryAccountId = (int) $destinationAccount->id;
                    $treasuryStatus = (string) ($transferRequest->status ?? 'requested');
                    $treasuryMessage = 'Solicitud de efectivo #' . $treasuryTransferRequestId . ' creada para aprobación.';

                    \Illuminate\Support\Facades\DB::table('pos_cash_movements')
                        ->where('id', $movementId)
                        ->update([
                            'treasury_transfer_request_id' => $treasuryTransferRequestId,
                            'destination_treasury_account_id' => $destinationTreasuryAccountId,
                            'treasury_status' => $treasuryStatus,
                            'updated_at' => now(),
                        ]);
                }

                return $movementId;
            });
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'message' => $type === 'cash_out'
                    ? 'No se pudo registrar el retiro. Revisa que el PDV tenga Caja PDV y que su sucursal tenga Caja sucursal configurada.'
                    : 'No se pudo registrar el movimiento de efectivo.',
                'error' => $e->getMessage(),
            ], 422);
        }

        $message = $type === 'cash_in'
            ? 'Entrada de efectivo registrada.'
            : 'Retiro de efectivo registrado.';

        if ($treasuryMessage) {
            $message .= ' ' . $treasuryMessage;
        }

        return response()->json([
            'ok' => true,
            'movement_id' => $movementId,
            'number' => $number,
            'print_url' => url('/pos/cash-movements/' . $movementId . '/print'),
            'treasury_transfer_request_id' => $treasuryTransferRequestId,
            'destination_treasury_account_id' => $destinationTreasuryAccountId,
            'treasury_status' => $treasuryStatus,
            'message' => $message,
        ]);
    }

    private function v5690d3TreasuryCashOutContext(object $sessionRow, object $pos): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('treasury_accounts')) {
            return ['error' => 'No existe la tabla de cuentas/cajas de Tesorería.'];
        }

        $companyId = (int) ($sessionRow->company_id ?? $pos->company_id ?? 0);
        $posPointId = (int) ($sessionRow->pos_point_id ?? $pos->id ?? 0);
        $warehouseId = ! empty($pos->warehouse_id) ? (int) $pos->warehouse_id : null;
        $branchId = null;

        if ($companyId <= 0) {
            return ['error' => 'La sesión PDV no tiene empresa válida.'];
        }

        if ($posPointId <= 0) {
            return ['error' => 'La sesión PDV no tiene punto de venta válido.'];
        }

        if (! empty($pos->branch_id)) {
            $branchId = (int) $pos->branch_id;
        }

        if (! $branchId && $warehouseId && \Illuminate\Support\Facades\Schema::hasTable('warehouses')) {
            $warehouse = \Illuminate\Support\Facades\DB::table('warehouses')
                ->where('id', $warehouseId)
                ->first();

            if ($warehouse && ! empty($warehouse->branch_id)) {
                $branchId = (int) $warehouse->branch_id;
            }
        }

        if (! $branchId) {
            return ['error' => 'Este PDV no tiene sucursal asociada. Configura la sucursal/tienda antes de registrar retiros.'];
        }

        $sourceAccount = \Illuminate\Support\Facades\DB::table('treasury_accounts')
            ->where('company_id', $companyId)
            ->where('pos_point_id', $posPointId)
            ->where('cash_scope', 'pdv')
            ->where('is_active', true)
            ->first();

        if (! $sourceAccount) {
            return ['error' => 'No existe una Caja PDV activa ligada a este punto de venta.'];
        }

        $destinationAccount = \Illuminate\Support\Facades\DB::table('treasury_accounts')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('cash_scope', 'branch_cash')
            ->where('is_active', true)
            ->first();

        if (! $destinationAccount) {
            return ['error' => 'No existe una Caja sucursal activa ligada a la sucursal de este PDV.'];
        }

        if ((int) $destinationAccount->id === (int) $sourceAccount->id) {
            return ['error' => 'La Caja PDV origen y la Caja sucursal destino no pueden ser la misma.'];
        }

        return [
            'source_account' => $sourceAccount,
            'destination_account' => $destinationAccount,
            'warehouse_id' => $warehouseId,
            'branch_id' => $branchId,
        ];
    }

    public function printCashMovement(\Illuminate\Http\Request $request, int $movement)
    {
        abort_unless(auth()->check(), 403);

        abort_unless(\Illuminate\Support\Facades\Schema::hasTable('pos_cash_movements'), 404);

        $row = \Illuminate\Support\Facades\DB::table('pos_cash_movements')
            ->where('id', $movement)
            ->first();

        abort_if(! $row, 404);

        $session = \Illuminate\Support\Facades\DB::table('pos_sessions')
            ->where('id', $row->pos_session_id)
            ->first();

        abort_if(! $session, 404);

        $pos = $this->posPoint((int) $session->pos_point_id);
        $this->authorizePos($pos);

        $typeLabel = (string) $row->type === 'cash_in'
            ? 'ENTRADA DE EFECTIVO'
            : 'RETIRO DE EFECTIVO';

        $posName = $pos->name
            ?? $pos->display_name
            ?? $pos->code
            ?? ('PDV #' . ($pos->id ?? ''));

        $companyName = $this->currentCompanyLabel((int) ($row->company_id ?? $session->company_id ?? 0));

        $html = view('pos.cash-movement-ticket', [
            'movement' => $row,
            'session' => $session,
            'posName' => $posName,
            'companyName' => $companyName,
            'typeLabel' => $typeLabel,
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }


    public function cashMovementEmployees(\Illuminate\Http\Request $request, int $session)
    {
        abort_unless(auth()->check(), 403);

        $sessionRow = \Illuminate\Support\Facades\DB::table('pos_sessions')
            ->where('id', $session)
            ->first();

        abort_if(! $sessionRow, 404, 'Sesión PDV no encontrada.');

        $pos = $this->posPoint((int) $sessionRow->pos_point_id);
        $this->authorizePos($pos);

        if (! \Illuminate\Support\Facades\Schema::hasTable('employees')) {
            return response()->json([
                'ok' => true,
                'employees' => [],
            ]);
        }

        $employeesQuery = \Illuminate\Support\Facades\DB::table('employees as e');

        if (\Illuminate\Support\Facades\Schema::hasTable('pos_point_employee')) {
            $employeesQuery
                ->join('pos_point_employee as pe', 'pe.employee_id', '=', 'e.id')
                ->where('pe.pos_point_id', (int) $pos->id)
                ->where('pe.is_active', true);
        }

        if (! empty($pos->company_id) && \Illuminate\Support\Facades\Schema::hasColumn('employees', 'company_id')) {
            $employeesQuery->where('e.company_id', (int) $pos->company_id);
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('employees', 'pos_active')) {
            $employeesQuery->where('e.pos_active', true);
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('employees', 'is_active')) {
            $employeesQuery->where('e.is_active', true);
        }

        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('employees');

        $nameExprs = [];

        foreach (['name', 'full_name', 'display_name'] as $column) {
            if (in_array($column, $columns, true)) {
                $nameExprs[] = "e.{$column}";
            }
        }

        $nameExpr = $nameExprs
            ? 'COALESCE(' . implode(', ', $nameExprs) . ", '')"
            : "('Empleado #' || e.id)";

        $select = [
            'e.id',
            \Illuminate\Support\Facades\DB::raw($nameExpr . ' as name'),
        ];

        if (in_array('employee_number', $columns, true)) {
            $select[] = 'e.employee_number';
        }

        if (in_array('code', $columns, true)) {
            $select[] = 'e.code';
        }

        $employees = $employeesQuery
            ->select($select)
            ->orderByRaw($nameExpr)
            ->limit(200)
            ->get()
            ->map(function ($row) {
                $code = $row->employee_number ?? $row->code ?? '';

                return [
                    'id' => (int) $row->id,
                    'name' => trim((string) $row->name) !== '' ? (string) $row->name : ('Empleado #' . $row->id),
                    'code' => (string) $code,
                    'label' => trim((string) $code) !== ''
                        ? trim((string) $code) . ' - ' . (trim((string) $row->name) !== '' ? (string) $row->name : ('Empleado #' . $row->id))
                        : (trim((string) $row->name) !== '' ? (string) $row->name : ('Empleado #' . $row->id)),
                ];
            })
            ->values();

        return response()->json([
            'ok' => true,
            'employees' => $employees,
        ]);
    }


    public function closeSessionRedirect(\Illuminate\Http\Request $request, int $session)
    {
        $row = \Illuminate\Support\Facades\DB::table('pos_sessions')
            ->where('id', $session)
            ->first();

        if ($row) {
            return redirect($this->v5485gPosEmployeeSelectorUrl($row))
                ->with('success', 'Sesión cerrada correctamente.');
        }

        $companyId = (int) (auth()->user()?->company_id ?? 0);

        if ($companyId > 0) {
            return redirect('/admin/' . $companyId . '/point-of-sale');
        }

        return redirect('/');
    }

    protected function v5485gPosEmployeeSelectorUrl(object $sessionRow): string
    {
        $companyId = (int) ($sessionRow->company_id ?? auth()->user()?->company_id ?? 0);

        /*
         * Esta es la pantalla donde se elige empleado/cajero para abrir o entrar al PDV.
         * Si más adelante cambiamos la ruta, solo se cambia aquí.
         */
        if ($companyId > 0) {
            return '/admin/' . $companyId . '/point-of-sale';
        }

        return '/';
    }


    protected function v5485hPosEmployeeSelectorUrl(object $sessionRow): string
    {
        $companyId = (int) ($sessionRow->company_id ?? auth()->user()?->company_id ?? 0);

        return $companyId > 0
            ? '/admin/' . $companyId . '/point-of-sale'
            : '/';
    }

    protected function v5488iClosePayloadFromSessionNotes(?string $notes): array
    {
        $notes = (string) $notes;

        if ($notes === '' || ! str_contains($notes, '[CIERRE PDV]')) {
            return [];
        }

        $payload = [];

        foreach (preg_split('/\r\n|\r|\n/', $notes) as $line) {
            $line = trim((string) $line);

            if (! str_starts_with($line, '[CIERRE PDV]')) {
                continue;
            }

            $json = trim(substr($line, strlen('[CIERRE PDV]')));
            $decoded = json_decode($json, true);

            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        if (isset($payload['cash_count']) && is_string($payload['cash_count'])) {
            $decodedCashCount = json_decode($payload['cash_count'], true);
            $payload['cash_count'] = is_array($decodedCashCount) ? $decodedCashCount : [];
        }

        return $payload;
    }


    protected function v5521c4ResolveAssetUrlFromValue($value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (
            str_starts_with($value, 'http://')
            || str_starts_with($value, 'https://')
            || str_starts_with($value, 'data:image/')
        ) {
            return $value;
        }

        if (str_starts_with($value, '/storage/')) {
            return url($value);
        }

        if (str_starts_with($value, 'storage/')) {
            return asset($value);
        }

        if (str_starts_with($value, 'public/')) {
            return asset('storage/' . ltrim(substr($value, 7), '/'));
        }

        if (str_starts_with($value, '/')) {
            return url($value);
        }

        try {
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($value)) {
                return asset('storage/' . ltrim($value, '/'));
            }
        } catch (\Throwable $e) {
        }

        try {
            if (\Illuminate\Support\Facades\Storage::exists($value)) {
                return \Illuminate\Support\Facades\Storage::url($value);
            }
        } catch (\Throwable $e) {
        }

        return asset(ltrim($value, '/'));
    }

    protected function v5521c4ResolveCompanyLogoUrl($pos = null, $sessionRow = null): ?string
    {
        $candidates = [];

        foreach ([
            'logo',
            'logo_path',
            'logo_url',
            'receipt_logo',
            'receipt_logo_path',
            'receipt_logo_url',
            'ticket_logo',
            'ticket_logo_path',
            'ticket_logo_url',
            'image',
            'image_path',
            'image_url',
        ] as $field) {
            if (is_object($pos) && isset($pos->{$field}) && ! empty($pos->{$field})) {
                $candidates[] = (string) $pos->{$field};
            }
        }

        $companyId = 0;

        if (is_object($pos) && isset($pos->company_id) && ! empty($pos->company_id)) {
            $companyId = (int) $pos->company_id;
        }

        if ($companyId <= 0 && is_object($sessionRow) && isset($sessionRow->company_id) && ! empty($sessionRow->company_id)) {
            $companyId = (int) $sessionRow->company_id;
        }

        if ($companyId > 0 && \Illuminate\Support\Facades\Schema::hasTable('companies')) {
            $company = \Illuminate\Support\Facades\DB::table('companies')
                ->where('id', $companyId)
                ->first();

            if ($company) {
                foreach ([
                    'logo',
                    'logo_path',
                    'logo_url',
                    'image',
                    'image_path',
                    'image_url',
                ] as $field) {
                    if (isset($company->{$field}) && ! empty($company->{$field})) {
                        $candidates[] = (string) $company->{$field};
                    }
                }
            }
        }

        foreach ($candidates as $candidate) {
            $url = $this->v5521c4ResolveAssetUrlFromValue($candidate);

            if (! empty($url)) {
                return $url;
            }
        }

        return null;
    }


    public function printCloseSessionTicket(\Illuminate\Http\Request $request, int $session)
    {
        abort_unless(auth()->check(), 403);

        $sessionRow = \Illuminate\Support\Facades\DB::table('pos_sessions')
            ->where('id', $session)
            ->first();

        abort_if(! $sessionRow, 404, 'Sesión PDV no encontrada.');

        $pos = $this->posPoint((int) $sessionRow->pos_point_id);
        $this->authorizePos($pos);

        $summary = $this->v5484BuildCloseSessionSummary($session);
        $closePayload = $this->v5488iClosePayloadFromSessionNotes($sessionRow->notes ?? null);

        $v5521eUrlFormatKey = strtolower(trim((string) $request->query('format', $request->query('close_format', ''))));
        $v5521eConfiguredFormatKey = strtolower(trim((string) ($pos->session_close_format ?? '')));
        $v5521eFormatKey = $v5521eUrlFormatKey !== '' ? $v5521eUrlFormatKey : $v5521eConfiguredFormatKey;
        $v5521eCloseFormat = in_array($v5521eFormatKey, ['papelon', 'papelón'], true) ? 'papelon' : 'generic';
        $v5521eView = 'pos.session-close-ticket';

        if ($v5521eCloseFormat === 'papelon') {
            $summary['papelon_close'] = \App\Support\PosPapelonCloseSummary::build($session);

            if (view()->exists('pos.session-close-ticket-papelon')) {
                $v5521eView = 'pos.session-close-ticket-papelon';
            }
        }

        $v5521eLogoUrl = $summary['company']['logo_url'] ?? null;

        if (! $v5521eLogoUrl && method_exists($this, 'ticketLogoUrl')) {
            $v5521eLogoUrl = $this->ticketLogoUrl($pos);
        }

        if (! $v5521eLogoUrl && method_exists($this, 'v5521c4ResolveCompanyLogoUrl')) {
            $v5521eLogoUrl = $this->v5521c4ResolveCompanyLogoUrl($pos, $sessionRow);
        }

        return response(view($v5521eView, [
            'summary' => $summary,
            'session' => $sessionRow,
            'closePayload' => $closePayload,
            'closeFormat' => $v5521eCloseFormat,
            'companyLogoUrl' => $v5521eLogoUrl,
        ])->render(), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }


    public function storePriceListChangeAudit(\Illuminate\Http\Request $request, int $session)
    {
        abort_unless(auth()->check(), 403);

        if (! \Illuminate\Support\Facades\Schema::hasTable('pos_price_list_changes')) {
            return response()->json([
                'ok' => false,
                'message' => 'No existe la tabla de auditoría de listas de precios.',
            ], 500);
        }

        $sessionRow = \Illuminate\Support\Facades\DB::table('pos_sessions')
            ->where('id', $session)
            ->first();

        if (! $sessionRow) {
            return response()->json([
                'ok' => false,
                'message' => 'Sesión PDV no encontrada.',
            ], 404);
        }

        $pos = \Illuminate\Support\Facades\DB::table('pos_points')
            ->where('id', (int) ($sessionRow->pos_point_id ?? 0))
            ->first();

        if ($pos && method_exists($this, 'authorizePos')) {
            $this->authorizePos($pos);
        }

        $previousId = $request->input('previous_price_list_id');
        $newId = $request->input('new_price_list_id', $request->input('price_list_id'));

        $previousId = is_numeric($previousId) && (int) $previousId > 0 ? (int) $previousId : null;
        $newId = is_numeric($newId) && (int) $newId > 0 ? (int) $newId : null;

        $previousName = trim((string) $request->input('previous_price_list_name', ''));
        $newName = trim((string) $request->input('new_price_list_name', $request->input('price_list_name', '')));
        $source = trim((string) $request->input('source', 'manual'));
        $source = $source !== '' ? mb_substr($source, 0, 80) : 'manual';

        $customerId = $request->input('customer_id');
        $customerId = is_numeric($customerId) && (int) $customerId > 0 ? (int) $customerId : null;

        if ($previousId === $newId && $previousName === $newName) {
            return response()->json([
                'ok' => true,
                'skipped' => true,
                'message' => 'Sin cambio de lista.',
            ]);
        }

        $metadata = [
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'payload' => $request->only([
                'source',
                'previous_price_list_id',
                'previous_price_list_name',
                'new_price_list_id',
                'new_price_list_name',
                'price_list_id',
                'price_list_name',
                'customer_id',
            ]),
        ];

        $id = \Illuminate\Support\Facades\DB::table('pos_price_list_changes')->insertGetId([
            'company_id' => $sessionRow->company_id ?? ($pos->company_id ?? null),
            'pos_point_id' => $sessionRow->pos_point_id ?? null,
            'pos_session_id' => $sessionRow->id,
            'user_id' => auth()->id(),
            'customer_id' => $customerId,
            'previous_price_list_id' => $previousId,
            'previous_price_list_name' => $previousName !== '' ? $previousName : null,
            'new_price_list_id' => $newId,
            'new_price_list_name' => $newName !== '' ? $newName : null,
            'source' => $source,
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'id' => $id,
        ]);
    }

    public function customerPriceListForSession(\Illuminate\Http\Request $request, int $session, int $customer)
    {
        abort_unless(auth()->check(), 403);

        $sessionRow = \Illuminate\Support\Facades\DB::table('pos_sessions')
            ->where('id', $session)
            ->first();

        if (! $sessionRow) {
            return response()->json([
                'ok' => false,
                'message' => 'Sesión PDV no encontrada.',
            ], 404);
        }

        $pos = \Illuminate\Support\Facades\DB::table('pos_points')
            ->where('id', (int) ($sessionRow->pos_point_id ?? 0))
            ->first();

        if (! $pos) {
            return response()->json([
                'ok' => false,
                'message' => 'Punto de venta no encontrado.',
            ], 404);
        }

        if (method_exists($this, 'authorizePos')) {
            $this->authorizePos($pos);
        }

        $contact = \Illuminate\Support\Facades\DB::table('contacts')
            ->where('id', $customer)
            ->first();

        if (! $contact) {
            return response()->json([
                'ok' => false,
                'message' => 'Cliente no encontrado.',
            ], 404);
        }

        $defaultPriceListId = (int) ($pos->default_price_list_id ?? 0);
        $customerPriceListId = (int) ($contact->customer_price_list_id ?? 0);

        $allowedIds = [];

        $rawAllowed = $pos->available_price_list_ids ?? [];

        if (is_string($rawAllowed)) {
            $decoded = json_decode($rawAllowed, true);
            $allowedIds = is_array($decoded) ? $decoded : preg_split('/\s*,\s*/', $rawAllowed);
        } elseif (is_array($rawAllowed)) {
            $allowedIds = $rawAllowed;
        }

        $allowedIds = collect($allowedIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($defaultPriceListId > 0 && ! in_array($defaultPriceListId, $allowedIds, true)) {
            $allowedIds[] = $defaultPriceListId;
        }

        $customerListAllowed = $customerPriceListId > 0
            && (empty($allowedIds) || in_array($customerPriceListId, $allowedIds, true));

        $selectedPriceListId = $customerListAllowed
            ? $customerPriceListId
            : $defaultPriceListId;

        $selectedPriceListName = $this->v5495cPriceListName($selectedPriceListId);

        return response()->json([
            'ok' => true,
            'session_id' => (int) $sessionRow->id,
            'customer_id' => (int) $contact->id,
            'customer_name' => (string) ($contact->name ?? 'Cliente'),
            'customer_price_list_id' => $customerPriceListId,
            'customer_price_list_allowed' => $customerListAllowed,
            'selected_price_list_id' => $selectedPriceListId,
            'selected_price_list_name' => $selectedPriceListName,
            'fallback_to_default' => $customerPriceListId > 0 && ! $customerListAllowed,
            'message' => $customerPriceListId > 0 && ! $customerListAllowed
                ? 'La lista del cliente no está permitida en este PDV. Se usará la lista predeterminada.'
                : null,
        ]);
    }

    protected function v5495cPriceListName(int $priceListId): string
    {
        if ($priceListId <= 0 || ! \Illuminate\Support\Facades\Schema::hasTable('sales_price_lists')) {
            return 'Precio público';
        }

        $name = \Illuminate\Support\Facades\DB::table('sales_price_lists')
            ->where('id', $priceListId)
            ->value('name');

        return $name ? (string) $name : ('Lista #' . $priceListId);
    }

public function refreshSessionProducts(\Illuminate\Http\Request $request, int $session)
    {
        abort_unless(auth()->check(), 403);

        $sessionRow = \Illuminate\Support\Facades\DB::table('pos_sessions')
            ->where('id', $session)
            ->first();

        abort_if(! $sessionRow, 404, 'Sesión PDV no encontrada.');

        $pos = $this->posPoint((int) $sessionRow->pos_point_id);
        $this->authorizePos($pos);

        $companyId = (int) ($sessionRow->company_id ?? $pos->company_id ?? 0);
        $warehouseId = (int) ($pos->warehouse_id ?? $sessionRow->warehouse_id ?? 0);
        $selectedPriceListId = $this->v5491bResolveRequestedPriceListId($request, $pos);

        // V5.51.5O - Auditoría backend cambio lista precios desde products-refresh.
        try {
            $v5515oRequestedPriceList = $request->query('price_list_id', $request->input('price_list_id', null));
            $v5515oPreviousPriceListId = $request->query('previous_price_list_id', $request->input('previous_price_list_id', null));
            $v5515oPreviousPriceListName = trim((string) $request->query('previous_price_list_name', $request->input('previous_price_list_name', '')));
            $v5515oSource = (string) $request->query('price_list_change_source', $request->input('price_list_change_source', 'products-refresh'));

            if ($v5515oRequestedPriceList !== null && $v5515oPreviousPriceListId !== null) {
                $v5515oOldId = is_numeric($v5515oPreviousPriceListId) ? (int) $v5515oPreviousPriceListId : 0;
                $v5515oNewId = is_numeric($selectedPriceListId ?? null) ? (int) $selectedPriceListId : 0;

                if ($v5515oOldId !== $v5515oNewId) {
                    $v5515oNewName = '';

                    try {
                        if (method_exists($this, 'v5491bPriceListLabel')) {
                            $v5515oNewName = (string) $this->v5491bPriceListLabel($v5515oNewId);
                        }
                    } catch (\Throwable $e) {
                        $v5515oNewName = '';
                    }

                    if ($v5515oNewName === '') {
                        $v5515oNewName = $v5515oNewId > 0 ? ('Lista #' . $v5515oNewId) : 'Precio público';
                    }

                    if ($v5515oPreviousPriceListName === '') {
                        $v5515oPreviousPriceListName = $v5515oOldId > 0 ? ('Lista #' . $v5515oOldId) : 'Precio público';
                    }

                    $v5515oCompanyId = null;

                    if (isset($sessionRow) && is_object($sessionRow) && isset($sessionRow->company_id)) {
                        $v5515oCompanyId = (int) $sessionRow->company_id;
                    } elseif (isset($pos) && is_object($pos) && isset($pos->company_id)) {
                        $v5515oCompanyId = (int) $pos->company_id;
                    }

                    $v5515oSessionId = isset($session) && is_numeric($session) ? (int) $session : null;

                    if (method_exists($this, 'v5515aWritePosAuditLog')) {
                        $this->v5515aWritePosAuditLog('pos.price_list.change', [
                            'company_id' => $v5515oCompanyId,
                            'user_id' => auth()->id(),
                            'pos_session_id' => $v5515oSessionId,
                            'entity_type' => 'pos_session',
                            'entity_id' => $v5515oSessionId,
                            'description' => 'Cambio de lista de precios en PDV.',
                            'before_data' => [
                                'price_list_id' => $v5515oOldId,
                                'price_list_label' => $v5515oPreviousPriceListName,
                            ],
                            'after_data' => [
                                'price_list_id' => $v5515oNewId,
                                'price_list_label' => $v5515oNewName,
                            ],
                            'metadata' => [
                                'source' => 'v5515o_backend_refreshSessionProducts',
                                'ui_source' => $v5515oSource,
                                'requested_price_list_id' => $v5515oRequestedPriceList,
                                'route' => optional($request->route())->getName(),
                                'url' => $request->fullUrl(),
                            ],
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('No se pudo auditar cambio backend de lista de precios PDV.', [
                'error' => $e->getMessage(),
            ]);
        }

        $priceLists = $this->v5491bPriceListsForPos($pos);

        /*
         * product_ids es opcional.
         * Si viene, refrescamos solo esos productos.
         * Si no viene, regresamos todos los productos vendibles del PDV.
         */
        $productIdsRaw = $request->query('product_ids', []);

        if (is_string($productIdsRaw)) {
            $productIds = collect(explode(',', $productIdsRaw))
                ->map(fn ($id) => (int) trim($id))
                ->filter()
                ->unique()
                ->values();
        } elseif (is_array($productIdsRaw)) {
            $productIds = collect($productIdsRaw)
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values();
        } else {
            $productIds = collect();
        }

        $productsQuery = \Illuminate\Support\Facades\DB::table('products as p');

        if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'company_id') && $companyId > 0) {
            $productsQuery->where(function ($query) use ($companyId) {
                $query->whereNull('p.company_id')
                    ->orWhere('p.company_id', $companyId);
            });
        }

        foreach (['is_active', 'active'] as $activeColumn) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('products', $activeColumn)) {
                $productsQuery->where("p.$activeColumn", true);
                break;
            }
        }

        // BEXIA_V5820E2A1_REFRESH_PRODUCTS_AVAILABLE_IN_POS
        // Mantener consistencia con la carga inicial del POS.
        if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'available_in_pos')) {
            $productsQuery->where(function ($query): void {
                $query->where('p.available_in_pos', true)
                    ->orWhereNull('p.available_in_pos');
            });
        }

        if ($productIds->isNotEmpty()) {
            $productsQuery->whereIn('p.id', $productIds);
        }

        $productColumns = \Illuminate\Support\Facades\Schema::getColumnListing('products');

        $select = ['p.id'];

        foreach (['name', 'display_name'] as $column) {
            if (in_array($column, $productColumns, true)) {
                $select[] = "p.$column";
            }
        }

        foreach (['sku', 'code', 'barcode'] as $column) {
            if (in_array($column, $productColumns, true)) {
                $select[] = "p.$column";
            }
        }

        // BEXIA_V5545G_SELECT_PARENT_TRACKING_FOR_SERIAL_STOCK
        // Necesario para calcular stock visual de variantes serializadas.
        foreach (['parent_product_id', 'tracking', 'advanced_tracking_mode'] as $column) {
            if (in_array($column, $productColumns, true)) {
                $select[] = "p.$column";
            }
        }

        foreach (['sale_price', 'price', 'list_price', 'public_price', 'sale_tax_rate'] as $column) {
            if (in_array($column, $productColumns, true)) {
                $select[] = "p.$column";
            }
        }

        $products = $productsQuery
            ->select($select)
            ->orderBy('p.id')
            ->limit($productIds->isNotEmpty() ? 500 : 2000)
            ->get();

        $ids = $products->pluck('id')->map(fn ($id) => (int) $id)->filter()->values();

        $stockByProduct = collect();

        // BEXIA_V5545G_SERIAL_AVAILABLE_BY_PRODUCT_VARIANT
        // Para productos con número de serie, el stock visible en PDV debe ser el conteo de series available.
        $productStockPairs = $products
            ->map(function ($product): array {
                $parentId = ! empty($product->parent_product_id) ? (int) $product->parent_product_id : 0;

                return [
                    'product_id' => $parentId > 0 ? $parentId : (int) $product->id,
                    'product_variant_id' => $parentId > 0 ? (int) $product->id : null,
                ];
            })
            ->filter(fn (array $pair): bool => (int) $pair['product_id'] > 0)
            ->unique(fn (array $pair): string => ((int) $pair['product_id']) . ':' . ((int) ($pair['product_variant_id'] ?? 0)))
            ->values();

        $serialAvailableByProductVariant = collect();

        if (
            $productStockPairs->isNotEmpty()
            && \Illuminate\Support\Facades\Schema::hasTable('stock_serial_numbers')
            && \Illuminate\Support\Facades\Schema::hasColumn('stock_serial_numbers', 'product_id')
        ) {
            $serialProductIds = $productStockPairs
                ->pluck('product_id')
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values();

            $serialQuery = \Illuminate\Support\Facades\DB::table('stock_serial_numbers')
                ->whereIn('product_id', $serialProductIds)
                ->where('status', 'available');

            if ($companyId > 0 && \Illuminate\Support\Facades\Schema::hasColumn('stock_serial_numbers', 'company_id')) {
                $serialQuery->where('company_id', $companyId);
            }

            if ($warehouseId > 0 && \Illuminate\Support\Facades\Schema::hasColumn('stock_serial_numbers', 'current_warehouse_id')) {
                $serialQuery->where('current_warehouse_id', $warehouseId);
            }

            if (! empty($locationId) && \Illuminate\Support\Facades\Schema::hasColumn('stock_serial_numbers', 'current_location_id')) {
                $serialQuery->where('current_location_id', $locationId);
            }

            $serialRows = $serialQuery
                ->selectRaw('product_id, product_variant_id, COUNT(*) as available_serials')
                ->groupBy('product_id', 'product_variant_id')
                ->get();

            $serialAvailableByProductVariant = $serialRows->keyBy(function ($row): string {
                return ((int) $row->product_id) . ':' . ((int) ($row->product_variant_id ?? 0));
            });
        }

        if (
            $ids->isNotEmpty()
            && \Illuminate\Support\Facades\Schema::hasTable('stock_quants')
            && \Illuminate\Support\Facades\Schema::hasColumn('stock_quants', 'product_id')
        ) {
            $stockQuery = \Illuminate\Support\Facades\DB::table('stock_quants')
                ->whereIn('product_id', $ids);

            if ($companyId > 0 && \Illuminate\Support\Facades\Schema::hasColumn('stock_quants', 'company_id')) {
                $stockQuery->where('company_id', $companyId);
            }

            if ($warehouseId > 0 && \Illuminate\Support\Facades\Schema::hasColumn('stock_quants', 'warehouse_id')) {
                $stockQuery->where('warehouse_id', $warehouseId);
            }

            $quantityColumn = \Illuminate\Support\Facades\Schema::hasColumn('stock_quants', 'quantity')
                ? 'quantity'
                : null;

            $reservedColumn = \Illuminate\Support\Facades\Schema::hasColumn('stock_quants', 'reserved_quantity')
                ? 'reserved_quantity'
                : null;

            $stockRows = $stockQuery
                ->selectRaw('product_id'
                    . ($quantityColumn ? ", SUM($quantityColumn) as quantity" : ', 0 as quantity')
                    . ($reservedColumn ? ", SUM($reservedColumn) as reserved_quantity" : ', 0 as reserved_quantity')
                )
                ->groupBy('product_id')
                ->get();

            $stockByProduct = $stockRows->keyBy(fn ($row) => (int) $row->product_id);
        }

        $payload = $products->map(function ($product) use ($productColumns, $stockByProduct, $serialAvailableByProductVariant, $selectedPriceListId) {
            $stock = $stockByProduct->get((int) $product->id);

            $quantity = round((float) ($stock->quantity ?? 0), 4);
            $reserved = round((float) ($stock->reserved_quantity ?? 0), 4);
            $available = round($quantity - $reserved, 4);

            // BEXIA_V5545G_USE_SERIAL_COUNT_AS_VISIBLE_STOCK
            // Si esta fila es una variante serializada, mostrar el conteo real de series disponibles.
            $parentIdForStock = ! empty($product->parent_product_id) ? (int) $product->parent_product_id : 0;
            $productIdForSerialStock = $parentIdForStock > 0 ? $parentIdForStock : (int) $product->id;
            $variantIdForSerialStock = $parentIdForStock > 0 ? (int) $product->id : 0;
            $serialStockKey = $productIdForSerialStock . ':' . $variantIdForSerialStock;

            if ($serialAvailableByProductVariant->has($serialStockKey)) {
                $serialStock = (float) ($serialAvailableByProductVariant->get($serialStockKey)->available_serials ?? 0);

                $quantity = $serialStock;
                $reserved = 0.0;
                $available = $serialStock;
            }

            $basePrice = 0.0;

            foreach (['sale_price', 'price', 'list_price', 'public_price'] as $column) {
                if (in_array($column, $productColumns, true) && isset($product->{$column})) {
                    $basePrice = round((float) $product->{$column}, 4);
                    break;
                }
            }

            /*
             * En productos como Copia B&N, el precio público capturado $1.50
             * se guarda en products.sale_price sin IVA: 1.2931.
             * El PDV debe refrescar y mostrar precio público con IVA incluido.
             */
            $taxRate = 0.0;

            if (in_array('sale_tax_rate', $productColumns, true) && isset($product->sale_tax_rate)) {
                $taxRate = round((float) $product->sale_tax_rate, 4);
            }

            $basePrice = $this->v5491bPriceWithoutTaxFromList((int) $product->id, $selectedPriceListId, $basePrice);

            $price = $basePrice;

            if ($basePrice > 0 && $taxRate > 0) {
                $price = round($basePrice * (1 + ($taxRate / 100)), 2);
            } else {
                $price = round($basePrice, 2);
            }

            $name = $product->name
                ?? $product->display_name
                ?? ('Producto #' . $product->id);

            return [
                'id' => (int) $product->id,
                'name' => (string) $name,
                'sku' => (string) ($product->sku ?? $product->code ?? $product->barcode ?? ''),
                'price' => $price,
                'public_price' => $price,
                'price_list_id' => $selectedPriceListId,
                'price_without_tax' => round($basePrice, 4),
                'sale_tax_rate' => $taxRate,
                'stock_quantity' => $quantity,
                'reserved_quantity' => $reserved,
                'available_quantity' => $available,
            ];
        })->values();

        // BEXIA_V5820E2A1_REFRESH_PRODUCTS_FILTER_ZERO_EFFECTIVE_PRICE
        // Filtra por precio efectivo ya calculado, incluyendo lista de precios si aplica.
        $payload = $payload
            ->filter(function (array $item): bool {
                return (float) ($item['price'] ?? $item['public_price'] ?? $item['price_without_tax'] ?? 0) > 0;
            })
            ->values();

        return response()->json([
            'ok' => true,
            'session_id' => (int) $sessionRow->id,
            'pos_point_id' => (int) $sessionRow->pos_point_id,
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'selected_price_list_id' => $selectedPriceListId,
            'selected_price_list_name' => $this->v5491bPriceListLabel($selectedPriceListId),
            'price_lists' => $priceLists,
            'count' => $payload->count(),
            'products' => $payload,
            'generated_at' => now()->toDateTimeString(),
        ]);
    }


    protected function v5491bDecodeJsonIds(mixed $value): array
    {
        if (is_array($value)) {
            return collect($value)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        }

        $value = trim((string) $value);

        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (is_array($decoded)) {
            return collect($decoded)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        }

        return collect(explode(',', $value))->map(fn ($id) => (int) trim($id))->filter()->unique()->values()->all();
    }

    protected function v5491bAllowedPriceListIds(object $pos): array
    {
        $ids = $this->v5491bDecodeJsonIds($pos->available_price_list_ids ?? []);

        $defaultId = (int) ($pos->default_price_list_id ?? 0);

        if ($defaultId > 0 && ! in_array($defaultId, $ids, true)) {
            array_unshift($ids, $defaultId);
        }

        return collect($ids)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
    }

    protected function v5491bPriceListLabel(int $id): string
    {
        if ($id <= 0) {
            return 'Precio público';
        }

        foreach (['sales_price_lists', 'sale_price_lists', 'price_lists', 'product_price_lists', 'pricelists'] as $table) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                continue;
            }

            $columns = \Illuminate\Support\Facades\Schema::getColumnListing($table);

            $labelColumn = collect(['name', 'display_name', 'title', 'code'])
                ->first(fn ($column) => in_array($column, $columns, true));

            if (! $labelColumn) {
                continue;
            }

            $row = \Illuminate\Support\Facades\DB::table($table)
                ->where('id', $id)
                ->first();

            if ($row && isset($row->{$labelColumn}) && trim((string) $row->{$labelColumn}) !== '') {
                return (string) $row->{$labelColumn};
            }
        }

        return 'Lista #' . $id;
    }

    protected function v5491bPriceListsForPos(object $pos): array
    {
        $allowedIds = $this->v5491bAllowedPriceListIds($pos);
        $defaultId = (int) ($pos->default_price_list_id ?? 0);

        if (empty($allowedIds)) {
            $name = trim((string) ($pos->price_list_name ?? 'Precio público'));

            return [[
                'id' => 0,
                'name' => $name !== '' ? $name : 'Precio público',
                'is_default' => true,
            ]];
        }

        return collect($allowedIds)
            ->map(function (int $id) use ($defaultId) {
                return [
                    'id' => $id,
                    'name' => $this->v5491bPriceListLabel($id),
                    'is_default' => $defaultId > 0 ? $id === $defaultId : false,
                ];
            })
            ->values()
            ->all();
    }

    protected function v5491bResolveRequestedPriceListId(\Illuminate\Http\Request $request, object $pos): int
    {
        $requested = (int) $request->query('price_list_id', $request->input('price_list_id', 0));
        $allowed = $this->v5491bAllowedPriceListIds($pos);
        $default = (int) ($pos->default_price_list_id ?? 0);

        if (empty($allowed)) {
            return 0;
        }

        if ($requested > 0 && in_array($requested, $allowed, true)) {
            return $requested;
        }

        if ($default > 0 && in_array($default, $allowed, true)) {
            return $default;
        }

        return (int) ($allowed[0] ?? 0);
    }

    protected function v5491bPriceWithoutTaxFromList(int $productId, int $priceListId, float $fallback, array $visited = []): float
    {
        if ($productId <= 0 || $priceListId <= 0) {
            return $fallback;
        }

        if (in_array($priceListId, $visited, true)) {
            return $fallback;
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('sales_price_lists')) {
            return $fallback;
        }

        $visited[] = $priceListId;

        $priceList = \Illuminate\Support\Facades\DB::table('sales_price_lists')
            ->where('id', $priceListId)
            ->first();

        if (! $priceList) {
            return $fallback;
        }

        $calculationType = (string) ($priceList->calculation_type ?? 'items');

        if ($calculationType === 'formula') {
            $baseListId = (int) ($priceList->base_price_list_id ?? 0);
            $adjustment = (float) ($priceList->adjustment_percent ?? 0);

            $base = $fallback;

            if ($baseListId > 0 && $baseListId !== $priceListId) {
                $base = $this->v5491bPriceWithoutTaxFromList($productId, $baseListId, $fallback, $visited);
            }

            return round($base * (1 + ($adjustment / 100)), 4);
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('sales_price_list_items')) {
            return $fallback;
        }

        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('sales_price_list_items');

        $listColumn = in_array('sales_price_list_id', $columns, true)
            ? 'sales_price_list_id'
            : (in_array('price_list_id', $columns, true) ? 'price_list_id' : null);

        if (! $listColumn || ! in_array('product_id', $columns, true)) {
            return $fallback;
        }

        $priceColumn = collect([
            'fixed_price',
            'price_without_tax',
            'sale_price',
            'unit_price',
            'price',
            'amount',
        ])->first(fn ($column) => in_array($column, $columns, true));

        if (! $priceColumn) {
            return $fallback;
        }

        $item = \Illuminate\Support\Facades\DB::table('sales_price_list_items')
            ->where($listColumn, $priceListId)
            ->where('product_id', $productId)
            ->orderByDesc('id')
            ->first();

        if (! $item || ! isset($item->{$priceColumn})) {
            return $fallback;
        }

        $price = (float) $item->{$priceColumn};

        return $price > 0 ? round($price, 4) : $fallback;
    }


    public function lotsForProduct(\Illuminate\Http\Request $request, int $session)
    {
        if (! auth()->check()) {
            return response()->json([
                'ok' => false,
                'message' => 'Tu sesión expiró. Vuelve a iniciar sesión.',
            ], 401);
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('pos_sessions')) {
            return response()->json([
                'ok' => false,
                'message' => 'No está disponible la tabla de sesiones PDV.',
            ], 422);
        }

        $sessionRow = \Illuminate\Support\Facades\DB::table('pos_sessions')
            ->where('id', $session)
            ->first();

        if (! $sessionRow) {
            return response()->json([
                'ok' => false,
                'message' => 'No se encontró la sesión PDV.',
            ], 404);
        }

        $pos = null;

        if (! empty($sessionRow->pos_point_id) && \Illuminate\Support\Facades\Schema::hasTable('pos_points')) {
            $pos = \Illuminate\Support\Facades\DB::table('pos_points')
                ->where('id', (int) $sessionRow->pos_point_id)
                ->first();
        }

        if ($pos && method_exists($this, 'authorizePos')) {
            try {
                $this->authorizePos($pos);
            } catch (\Throwable $e) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No tienes permiso para consultar este PDV.',
                ], 403);
            }
        }

        $companyId = (int) ($sessionRow->company_id ?? $pos->company_id ?? 0);
        $warehouseId = (int) ($pos->warehouse_id ?? 0);
        $locationId = (int) ($pos->stock_source_location_id ?? $pos->stock_location_id ?? 0);

        $productId = (int) $request->query('product_id', 0);
        $variantId = (int) $request->query('product_variant_id', $request->query('variant_id', 0));
        $selectedLotId = (int) $request->query('selected_lot_id', $request->query('stock_lot_id', 0));

        if ($productId <= 0) {
            return response()->json([
                'ok' => false,
                'message' => 'Producto inválido.',
            ], 422);
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('products')) {
            $product = \Illuminate\Support\Facades\DB::table('products')
                ->where('id', $productId)
                ->first();

            if (
                $product
                && ! $variantId
                && \Illuminate\Support\Facades\Schema::hasColumn('products', 'parent_product_id')
                && ! empty($product->parent_product_id)
            ) {
                $variantId = $productId;
                $productId = (int) $product->parent_product_id;
            }
        }

        $requiresLot = false;

        if (\Illuminate\Support\Facades\Schema::hasTable('products')) {
            $ids = array_values(array_unique(array_filter([$productId, $variantId])));

            foreach ($ids as $id) {
                $row = \Illuminate\Support\Facades\DB::table('products')
                    ->where('id', (int) $id)
                    ->first();

                if (! $row) {
                    continue;
                }

                foreach (['tracking', 'advanced_tracking_mode'] as $column) {
                    $value = strtolower(trim((string) ($row->{$column} ?? '')));

                    if ($value !== '' && (str_contains($value, 'lot') || str_contains($value, 'lote'))) {
                        $requiresLot = true;
                    }
                }
            }
        }

        $lots = collect();

        if (
            $companyId > 0
            && $warehouseId > 0
            && $locationId > 0
            && \Illuminate\Support\Facades\Schema::hasTable('stock_lots')
            && \Illuminate\Support\Facades\Schema::hasTable('stock_quants')
        ) {
            $query = \Illuminate\Support\Facades\DB::table('stock_lots as l')
                ->join('stock_quants as q', 'q.lot_id', '=', 'l.id')
                ->where('l.company_id', $companyId)
                ->where('q.company_id', $companyId)
                ->where('l.product_id', $productId)
                ->where('q.product_id', $productId)
                ->where('q.warehouse_id', $warehouseId)
                ->where('q.location_id', $locationId)
                ->where('q.quantity', '>', 0)
                ->where(function ($inner) use ($selectedLotId): void {
                    $inner->whereRaw('(q.quantity - q.reserved_quantity) > 0');

                    if ($selectedLotId > 0) {
                        $inner->orWhere('l.id', $selectedLotId);
                    }
                });

            if ($variantId > 0 && \Illuminate\Support\Facades\Schema::hasColumn('stock_lots', 'product_variant_id')) {
                $query->where('l.product_variant_id', $variantId)
                    ->where('q.product_variant_id', $variantId);
            } else {
                $query->where(function ($inner): void {
                    $inner->whereNull('l.product_variant_id')
                        ->orWhere('l.product_variant_id', 0);
                })->whereNull('q.product_variant_id');
            }

            $lotRows = $query
                ->select([
                    'l.id',
                    'l.lot_number',
                    'l.product_id',
                    'l.product_variant_id',
                    'l.expiration_date',
                    'q.quantity',
                    'q.reserved_quantity',
                ])
                ->orderBy('l.expiration_date')
                ->orderBy('l.id')
                ->limit(100)
                ->get();

            if ($selectedLotId > 0 && ! $lotRows->contains('id', $selectedLotId)) {
                $selectedLot = \Illuminate\Support\Facades\DB::table('stock_lots')
                    ->where('id', $selectedLotId)
                    ->where('company_id', $companyId)
                    ->where('product_id', $productId)
                    ->first();

                if (
                    $selectedLot
                    && (
                        $variantId <= 0
                        || empty($selectedLot->product_variant_id)
                        || (int) $selectedLot->product_variant_id === $variantId
                    )
                ) {
                    $lotRows->prepend($selectedLot);
                }
            }

            $lots = $lotRows
                ->map(function ($lot): array {
                    $available = max(0, (float) ($lot->quantity ?? 0) - (float) ($lot->reserved_quantity ?? 0));
                    $availableText = rtrim(rtrim(number_format($available, 2, '.', ','), '0'), '.');

                    return [
                        'id' => (int) $lot->id,
                        'stock_lot_id' => (int) $lot->id,
                        'lot_number' => (string) ($lot->lot_number ?? ''),
                        'label' => (string) (
                            ($lot->lot_number ?? ('Lote #' . $lot->id))
                            . (! empty($lot->expiration_date) ? ' · vence ' . $lot->expiration_date : '')
                            . ' · disp. ' . $availableText
                        ),
                        'product_id' => (int) ($lot->product_id ?? 0),
                        'product_variant_id' => ! empty($lot->product_variant_id) ? (int) $lot->product_variant_id : null,
                        'expiration_date' => $lot->expiration_date ?? null,
                        'available_quantity' => $available,
                    ];
                });
        }

        if ($lots->isNotEmpty()) {
            $requiresLot = true;
        }

        return response()->json([
            'ok' => true,
            'requires_lot' => $requiresLot,
            'product_id' => $productId,
            'product_variant_id' => $variantId ?: null,
            'lots' => $lots->values(),
        ]);
    }

    public function serialsForProduct(\Illuminate\Http\Request $request, int $session)
    {
        if (! auth()->check()) {
            return response()->json([
                'ok' => false,
                'message' => 'Tu sesión expiró. Vuelve a iniciar sesión.',
            ], 401);
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('pos_sessions')) {
            return response()->json([
                'ok' => false,
                'message' => 'No está disponible la tabla de sesiones PDV.',
            ], 422);
        }

        $sessionRow = \Illuminate\Support\Facades\DB::table('pos_sessions')
            ->where('id', $session)
            ->first();

        if (! $sessionRow) {
            return response()->json([
                'ok' => false,
                'message' => 'No se encontró la sesión PDV.',
            ], 404);
        }

        $pos = null;

        if (! empty($sessionRow->pos_point_id) && \Illuminate\Support\Facades\Schema::hasTable('pos_points')) {
            $pos = \Illuminate\Support\Facades\DB::table('pos_points')
                ->where('id', (int) $sessionRow->pos_point_id)
                ->first();
        }

        if ($pos && method_exists($this, 'authorizePos')) {
            try {
                $this->authorizePos($pos);
            } catch (\Throwable $e) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No tienes permiso para consultar este PDV.',
                ], 403);
            }
        }

        $companyId = (int) ($sessionRow->company_id ?? $pos->company_id ?? 0);
        $warehouseId = (int) ($pos->warehouse_id ?? 0);
        $locationId = (int) ($pos->stock_source_location_id ?? $pos->stock_location_id ?? 0);

        $productId = (int) $request->query('product_id', 0);
        $variantId = (int) $request->query('product_variant_id', $request->query('variant_id', 0));
        $selectedSerialId = (int) $request->query('selected_serial_id', $request->query('stock_serial_number_id', 0));

        if ($productId <= 0) {
            return response()->json([
                'ok' => false,
                'message' => 'Producto inválido.',
            ], 422);
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('products')) {
            $product = \Illuminate\Support\Facades\DB::table('products')
                ->where('id', $productId)
                ->first();

            if (
                $product
                && ! $variantId
                && \Illuminate\Support\Facades\Schema::hasColumn('products', 'parent_product_id')
                && ! empty($product->parent_product_id)
            ) {
                $variantId = $productId;
                $productId = (int) $product->parent_product_id;
            }
        }

        $requiresSerial = false;

        if (\Illuminate\Support\Facades\Schema::hasTable('products')) {
            $ids = array_values(array_unique(array_filter([$productId, $variantId])));

            foreach ($ids as $id) {
                $row = \Illuminate\Support\Facades\DB::table('products')
                    ->where('id', (int) $id)
                    ->first();

                if (! $row) {
                    continue;
                }

                foreach (['tracking', 'advanced_tracking_mode'] as $column) {
                    $value = strtolower(trim((string) ($row->{$column} ?? '')));

                    if ($value !== '' && (str_contains($value, 'serial') || str_contains($value, 'serie'))) {
                        $requiresSerial = true;
                    }
                }
            }
        }

        $serials = collect();

        if ($companyId > 0 && \Illuminate\Support\Facades\Schema::hasTable('stock_serial_numbers')) {
            $query = \Illuminate\Support\Facades\DB::table('stock_serial_numbers')
                ->where('company_id', $companyId)
                ->where('product_id', $productId)
                ->where('status', 'available');

            if ($variantId > 0 && \Illuminate\Support\Facades\Schema::hasColumn('stock_serial_numbers', 'product_variant_id')) {
                $query->where('product_variant_id', $variantId);
            }

            if ($warehouseId > 0) {
                if (\Illuminate\Support\Facades\Schema::hasColumn('stock_serial_numbers', 'current_warehouse_id')) {
                    $query->where(function ($inner) use ($warehouseId): void {
                        $inner->where('current_warehouse_id', $warehouseId)
                            ->orWhereNull('current_warehouse_id')
                            ->orWhere('current_warehouse_id', 0);
                    });
                } elseif (\Illuminate\Support\Facades\Schema::hasColumn('stock_serial_numbers', 'warehouse_id')) {
                    $query->where(function ($inner) use ($warehouseId): void {
                        $inner->where('warehouse_id', $warehouseId)
                            ->orWhereNull('warehouse_id')
                            ->orWhere('warehouse_id', 0);
                    });
                }
            }

            if ($locationId > 0) {
                if (\Illuminate\Support\Facades\Schema::hasColumn('stock_serial_numbers', 'current_location_id')) {
                    $query->where(function ($inner) use ($locationId): void {
                        $inner->where('current_location_id', $locationId)
                            ->orWhereNull('current_location_id')
                            ->orWhere('current_location_id', 0);
                    });
                } elseif (\Illuminate\Support\Facades\Schema::hasColumn('stock_serial_numbers', 'location_id')) {
                    $query->where(function ($inner) use ($locationId): void {
                        $inner->where('location_id', $locationId)
                            ->orWhereNull('location_id')
                            ->orWhere('location_id', 0);
                    });
                }
            }

            $serialRows = $query
                ->orderBy('serial_number')
                ->limit(100)
                ->get();

            // BEXIA_V5544E_SELECTED_PENDING_SERIAL_ONLY
            // Si se carga un ticket pendiente y ya trae serie, el selector debe mostrar solo esa serie.
            if ($selectedSerialId > 0) {
                $selectedSerialForPending = \Illuminate\Support\Facades\DB::table('stock_serial_numbers')
                    ->where('id', $selectedSerialId)
                    ->where('company_id', $companyId)
                    ->where('product_id', $productId)
                    ->first();

                if (
                    $selectedSerialForPending
                    && (
                        $variantId <= 0
                        || empty($selectedSerialForPending->product_variant_id)
                        || (int) $selectedSerialForPending->product_variant_id === $variantId
                    )
                ) {
                    $serialRows = collect([$selectedSerialForPending]);
                } else {
                    $serialRows = $serialRows
                        ->filter(function ($serial) use ($selectedSerialId): bool {
                            return (int) ($serial->id ?? 0) === $selectedSerialId;
                        })
                        ->values();
                }
            }

            // BEXIA_V5544_INCLUDE_SELECTED_PENDING_SERIAL
            // Si se carga un ticket pendiente, incluir la serie ya elegida aunque no esté en la lista normal de available.
            if ($selectedSerialId > 0 && ! $serialRows->contains('id', $selectedSerialId)) {
                $selectedSerial = \Illuminate\Support\Facades\DB::table('stock_serial_numbers')
                    ->where('id', $selectedSerialId)
                    ->where('company_id', $companyId)
                    ->where('product_id', $productId)
                    ->first();

                if (
                    $selectedSerial
                    && (
                        $variantId <= 0
                        || empty($selectedSerial->product_variant_id)
                        || (int) $selectedSerial->product_variant_id === $variantId
                    )
                ) {
                    $serialRows->prepend($selectedSerial);
                }
            }

            $serials = $serialRows
                ->map(function ($serial): array {
                    return [
                        'id' => (int) $serial->id,
                        'serial_number' => (string) ($serial->serial_number ?? ''),
                        'label' => (string) ($serial->serial_number ?? ('Serie #' . $serial->id)),
                        'product_id' => (int) ($serial->product_id ?? 0),
                        'product_variant_id' => ! empty($serial->product_variant_id) ? (int) $serial->product_variant_id : null,
                        'status' => (string) ($serial->status ?? ''),
                    ];
                });
        }

        if ($serials->isNotEmpty()) {
            $requiresSerial = true;
        }

        return response()->json([
            'ok' => true,
            'requires_serial' => $requiresSerial,
            'product_id' => $productId,
            'product_variant_id' => $variantId ?: null,
            'serials' => $serials->values(),
        ]);
    }


}
