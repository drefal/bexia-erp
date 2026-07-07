<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// V5_52_4B_ACCOUNTING_POST_INVOICE
Artisan::command('bexia:accounting-post-invoice {invoice_id}', function ($invoice_id) {
    if (! class_exists(\App\Models\Invoice::class)) {
        $this->error('No existe App\Models\Invoice. Revisa que el módulo de facturas internas esté instalado.');
        return self::FAILURE;
    }

    $invoice = \App\Models\Invoice::query()->find($invoice_id);

    if (! $invoice) {
        $this->error('No se encontró la factura interna ID: ' . $invoice_id);
        return self::FAILURE;
    }

    try {
        $entry = app(\App\Support\Accounting\InvoiceAccountingPoster::class)->post($invoice, null);

        $this->info('Factura contabilizada correctamente.');
        $this->line('Asiento ID: ' . $entry->id);
        $this->line('Número: ' . $entry->entry_number);
        $this->line('Debe: ' . $entry->total_debit);
        $this->line('Haber: ' . $entry->total_credit);

        return self::SUCCESS;
    } catch (Throwable $e) {
        $this->error($e->getMessage());

        return self::FAILURE;
    }
})->purpose('Genera asiento contable RC1 para una factura interna');

// V5_52_4C_ACCOUNTING_INVENTORY_PERPETUAL_COMMANDS
\Illuminate\Support\Facades\Artisan::command('bexia:accounting-inventory-map-check {company_id}', function ($company_id) {
    $companyId = (int) $company_id;

    try {
        $diagnosis = app(\App\Support\Accounting\InventoryAccountingPoster::class)->diagnoseMappings($companyId);

        foreach ($diagnosis as $operation => $sides) {
            $this->line('');
            $this->info('Operacion: ' . $operation);

            foreach ($sides as $side => $data) {
                if ($data['ok'] ?? false) {
                    $this->line(
                        '  ' . $side
                        . ' / ' . $data['mapping_key']
                        . ' => ' . ($data['account_code'] ?? '')
                        . ' ' . ($data['account_name'] ?? '')
                        . ' [ID ' . ($data['account_id'] ?? '') . ']'
                    );
                } else {
                    $this->error(
                        '  ' . $side
                        . ' / ' . $data['mapping_key']
                        . ' => FALTA: ' . ($data['error'] ?? 'sin detalle')
                    );
                }
            }
        }

        return self::SUCCESS;
    } catch (\Throwable $e) {
        $this->error($e->getMessage());

        return self::FAILURE;
    }
})->purpose('Diagnostica mapeos contables para inventario perpetuo RC1');

\Illuminate\Support\Facades\Artisan::command('bexia:accounting-post-inventory {company_id} {operation} {amount} {--source_type=manual_inventory} {--source_id=} {--source_line_id=} {--product_id=} {--quantity=} {--unit_cost=} {--currency=MXN} {--movement_date=} {--label=}', function ($company_id, $operation, $amount) {
    $payload = [
        'company_id' => (int) $company_id,
        'operation_type' => (string) $operation,
        'amount' => (float) $amount,
        'source_type' => (string) $this->option('source_type'),
        'source_id' => $this->option('source_id'),
        'source_line_id' => $this->option('source_line_id'),
        'product_id' => $this->option('product_id'),
        'quantity' => $this->option('quantity'),
        'unit_cost' => $this->option('unit_cost'),
        'currency' => (string) $this->option('currency'),
        'movement_date' => $this->option('movement_date'),
        'label' => $this->option('label') ?: null,
    ];

    try {
        $entry = app(\App\Support\Accounting\InventoryAccountingPoster::class)->post($payload, null);

        $this->info('Movimiento de inventario contabilizado correctamente.');
        $this->line('Asiento ID: ' . $entry->id);
        $this->line('Numero: ' . $entry->entry_number);
        $this->line('Debe: ' . $entry->total_debit);
        $this->line('Haber: ' . $entry->total_credit);

        return self::SUCCESS;
    } catch (\Throwable $e) {
        $this->error($e->getMessage());

        return self::FAILURE;
    }
})->purpose('Genera asiento de inventario perpetuo RC1');

// V5_52_4F_INVENTORY_PURCHASE_SALES_COMMANDS
\Illuminate\Support\Facades\Artisan::command('bexia:accounting-post-purchase-order-receipt {purchase_order_id} {--dry-run} {--force}', function ($purchase_order_id) {
    try {
        $summary = app(\App\Support\Accounting\InventoryDocumentAccountingPoster::class)
            ->postPurchaseOrderReceipt(
                (int) $purchase_order_id,
                (bool) $this->option('dry-run'),
                (bool) $this->option('force')
            );

        $this->info('Proceso compra/inventario terminado.');
        $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return empty($summary['errors']) ? self::SUCCESS : self::FAILURE;
    } catch (\Throwable $e) {
        $this->error($e->getMessage());

        return self::FAILURE;
    }
})->purpose('Contabiliza entrada de inventario desde líneas recibidas de una orden de compra');

\Illuminate\Support\Facades\Artisan::command('bexia:accounting-post-sales-order-cost {sales_order_id} {--dry-run} {--force}', function ($sales_order_id) {
    try {
        $summary = app(\App\Support\Accounting\InventoryDocumentAccountingPoster::class)
            ->postSalesOrderCost(
                (int) $sales_order_id,
                (bool) $this->option('dry-run'),
                (bool) $this->option('force')
            );

        $this->info('Proceso venta/costo terminado.');
        $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return empty($summary['errors']) ? self::SUCCESS : self::FAILURE;
    } catch (\Throwable $e) {
        $this->error($e->getMessage());

        return self::FAILURE;
    }
})->purpose('Contabiliza costo de venta desde líneas entregadas de una orden de venta');

// V5_52_4H_POS_INVENTORY_COMMANDS
\Illuminate\Support\Facades\Artisan::command('bexia:accounting-post-pos-order-cost {pos_order_id} {--dry-run} {--force}', function ($pos_order_id) {
    try {
        $summary = app(\App\Support\Accounting\InventoryPosAccountingPoster::class)
            ->postPosOrderCost(
                (int) $pos_order_id,
                (bool) $this->option('dry-run'),
                (bool) $this->option('force')
            );

        $this->info('Proceso POS/costo terminado.');
        $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return empty($summary['errors']) ? self::SUCCESS : self::FAILURE;
    } catch (\Throwable $e) {
        $this->error($e->getMessage());

        return self::FAILURE;
    }
})->purpose('Contabiliza costo de venta desde líneas de ticket POS pagado');

\Illuminate\Support\Facades\Artisan::command('bexia:accounting-post-pos-refund-return {pos_order_refund_id} {--dry-run} {--force}', function ($pos_order_refund_id) {
    try {
        $summary = app(\App\Support\Accounting\InventoryPosAccountingPoster::class)
            ->postPosRefundReturn(
                (int) $pos_order_refund_id,
                (bool) $this->option('dry-run'),
                (bool) $this->option('force')
            );

        $this->info('Proceso POS/devolución inventario terminado.');
        $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return empty($summary['errors']) ? self::SUCCESS : self::FAILURE;
    } catch (\Throwable $e) {
        $this->error($e->getMessage());

        return self::FAILURE;
    }
})->purpose('Contabiliza entrada de inventario por devolución de POS');


// V5_71_18F_SYNC_COMPANY_GROUP_ROLES_COMMAND
\Illuminate\Support\Facades\Artisan::command(
    'bexia:sync-company-group-roles
        {group : ID o nombre del grupo de empresas}
        {source_company : ID o nombre de la empresa plantilla dentro del grupo}
        {--role=* : ID o nombre exacto del rol a sincronizar. Repetible. Si se omite, sincroniza todos los roles de la empresa plantilla}
        {--role-contains : Permite que --role busque por coincidencia parcial en lugar de nombre exacto}
        {--dry-run : Solo muestra lo que haría, sin guardar cambios}
        {--skip-permissions : Crea roles faltantes sin sincronizar permisos}
        {--include-inactive : Incluye empresas inactivas del grupo}',
    function ($group, $source_company) {
        try {
            $groupQuery = trim((string) $group);
            $sourceCompanyQuery = trim((string) $source_company);
            $roleFilters = collect($this->option('role') ?? [])
                ->filter()
                ->map(fn ($value): string => trim((string) $value))
                ->values();

            $dryRun = (bool) $this->option('dry-run');
            $roleContains = (bool) $this->option('role-contains');
            $syncPermissions = ! (bool) $this->option('skip-permissions');
            $includeInactive = (bool) $this->option('include-inactive');

            $companyGroup = \Illuminate\Support\Facades\DB::table('company_groups')
                ->when(ctype_digit($groupQuery), fn ($query) => $query->where('id', (int) $groupQuery))
                ->when(! ctype_digit($groupQuery), fn ($query) => $query->where('name', 'ilike', '%' . $groupQuery . '%'))
                ->orderBy('name')
                ->first();

            if (! $companyGroup) {
                $this->error('No se encontró el grupo: ' . $groupQuery);

                return self::FAILURE;
            }

            $sourceCompany = \Illuminate\Support\Facades\DB::table('companies')
                ->where('company_group_id', $companyGroup->id)
                ->where(function ($query) use ($sourceCompanyQuery) {
                    if (ctype_digit($sourceCompanyQuery)) {
                        $query->where('id', (int) $sourceCompanyQuery);
                    } else {
                        $query->where('name', 'ilike', '%' . $sourceCompanyQuery . '%');
                    }
                })
                ->orderBy('name')
                ->first();

            if (! $sourceCompany) {
                $this->error('No se encontró la empresa plantilla dentro del grupo: ' . $sourceCompanyQuery);

                return self::FAILURE;
            }

            $sourceRolesQuery = \Spatie\Permission\Models\Role::query()
                ->where('company_id', $sourceCompany->id)
                ->orderBy('name');

            if ($roleFilters->isNotEmpty()) {
                $sourceRolesQuery->where(function ($query) use ($roleFilters, $roleContains) {
                    foreach ($roleFilters as $filter) {
                        $query->orWhere(function ($subQuery) use ($filter, $roleContains) {
                            if (ctype_digit($filter)) {
                                $subQuery->where('id', (int) $filter);
                            } elseif ($roleContains) {
                                $subQuery->where('name', 'ilike', '%' . $filter . '%');
                            } else {
                                $subQuery->where('name', $filter);
                            }
                        });
                    }
                });
            }

            $sourceRoles = $sourceRolesQuery->get();

            if ($sourceRoles->isEmpty()) {
                $this->error('No se encontraron roles origen para sincronizar.');

                return self::FAILURE;
            }

            $companiesQuery = \Illuminate\Support\Facades\DB::table('companies')
                ->where('company_group_id', $companyGroup->id)
                ->orderBy('name');

            if (! $includeInactive) {
                $companiesQuery->where('active', true);
            }

            $companies = $companiesQuery->get(['id', 'name', 'active']);

            if ($companies->isEmpty()) {
                $this->error('No se encontraron empresas destino en el grupo.');

                return self::FAILURE;
            }

            $this->line('');
            $this->info('Grupo: ' . $companyGroup->id . ' - ' . $companyGroup->name);
            $this->info('Empresa plantilla: ' . $sourceCompany->id . ' - ' . $sourceCompany->name);
            $this->line('Roles origen: ' . $sourceRoles->count());
            $this->line('Empresas destino: ' . $companies->count());
            $this->line('Modo: ' . ($dryRun ? 'DRY-RUN' : 'APLICAR CAMBIOS'));
            $this->line('Sincronizar permisos: ' . ($syncPermissions ? 'SI' : 'NO'));
            $this->line('Busqueda de roles: ' . ($roleContains ? 'PARCIAL (--role-contains)' : 'EXACTA'));
            $this->line('');

            $created = 0;
            $updated = 0;
            $unchanged = 0;
            $errors = 0;

            $execute = function () use ($sourceRoles, $companies, $syncPermissions, $dryRun, &$created, &$updated, &$unchanged, &$errors): void {
                foreach ($sourceRoles as $sourceRole) {
                    $permissionIds = \Illuminate\Support\Facades\DB::table('role_has_permissions')
                        ->where('role_id', $sourceRole->id)
                        ->pluck('permission_id')
                        ->map(fn ($id): int => (int) $id)
                        ->values()
                        ->all();

                    $this->line('ROL_BASE: ' . $sourceRole->name . ' | origen role_id=' . $sourceRole->id . ' | permisos=' . count($permissionIds));

                    foreach ($companies as $company) {
                        $existingRole = \Spatie\Permission\Models\Role::query()
                            ->where('name', $sourceRole->name)
                            ->where('guard_name', $sourceRole->guard_name)
                            ->where('company_id', $company->id)
                            ->first();

                        if ($existingRole) {
                            if ($syncPermissions) {
                                $this->line('  UPDATE_PERMISOS company_id=' . $company->id . ' ' . $company->name . ' role_id=' . $existingRole->id . ' permisos=' . count($permissionIds));

                                if (! $dryRun) {
                                    $existingRole->syncPermissions($permissionIds);
                                }

                                $updated++;
                            } else {
                                $this->line('  EXISTE company_id=' . $company->id . ' ' . $company->name . ' role_id=' . $existingRole->id);
                                $unchanged++;
                            }

                            continue;
                        }

                        $this->line('  CREAR company_id=' . $company->id . ' ' . $company->name . ' rol=' . $sourceRole->name . ' permisos=' . count($permissionIds));

                        if (! $dryRun) {
                            $newRole = \Spatie\Permission\Models\Role::query()->create([
                                'name' => $sourceRole->name,
                                'guard_name' => $sourceRole->guard_name,
                                'company_id' => $company->id,
                            ]);

                            if ($syncPermissions) {
                                $newRole->syncPermissions($permissionIds);
                            }
                        }

                        $created++;
                    }

                    $this->line('');
                }
            };

            if ($dryRun) {
                $execute();
            } else {
                \Illuminate\Support\Facades\DB::transaction($execute);
                app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            }

            $this->line('Resumen:');
            $this->line('  creados=' . $created);
            $this->line('  actualizados=' . $updated);
            $this->line('  existentes_sin_cambios=' . $unchanged);
            $this->line('  errores=' . $errors);

            return $errors === 0 ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
)->purpose('Sincroniza roles base desde una empresa plantilla hacia todas las empresas de un grupo');


