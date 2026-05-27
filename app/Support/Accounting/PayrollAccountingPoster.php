<?php

namespace App\Support\Accounting;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class PayrollAccountingPoster
{
    public const SOURCE_TYPE = 'payroll_run';
    public const REVERSAL_SOURCE_TYPE = 'payroll_run_reversal';

    public function setupDefaultMappings(int $companyId, ?int $userId = null): array
    {
        if ($companyId <= 0) {
            throw new RuntimeException('company_id es requerido.');
        }

        return DB::transaction(function () use ($companyId, $userId): array {
            $journal = $this->resolveOrCreateGeneralJournal($companyId);

            $accounts = [
                'payroll_expense' => $this->resolveOrCreateAccount(
                    $companyId,
                    '601.90',
                    'Sueldos y salarios',
                    'expense',
                    'debit',
                    'payroll_expense',
                    'Cuenta de gasto para nómina generada por V5.67.0b.'
                ),
                'payroll_payable' => $this->resolveOrCreateAccount(
                    $companyId,
                    '210.90',
                    'Sueldos por pagar',
                    'liability',
                    'credit',
                    'payroll_payable',
                    'Pasivo por nómina neta por pagar generado por V5.67.0b.'
                ),
                'payroll_tax_withholding_payable' => $this->resolveOrCreateAccount(
                    $companyId,
                    '210.91',
                    'Retenciones de nómina por pagar',
                    'liability',
                    'credit',
                    'payroll_tax_withholding_payable',
                    'Pasivo por ISR/IMSS/retenciones de nómina generado por V5.67.0b.'
                ),
                'payroll_deduction_payable' => $this->resolveOrCreateAccount(
                    $companyId,
                    '210.92',
                    'Deducciones de nómina por pagar',
                    'liability',
                    'credit',
                    'payroll_deduction_payable',
                    'Pasivo por deducciones de nómina generado por V5.67.0b.'
                ),
            ];

            if (Schema::hasTable('accounting_mappings')) {
                foreach ($accounts as $key => $account) {
                    DB::table('accounting_mappings')->updateOrInsert(
                        [
                            'company_id' => $companyId,
                            'module' => 'payroll',
                            'operation_type' => 'payroll_run',
                            'mapping_key' => $key,
                        ],
                        [
                            'account_id' => $account->id,
                            'is_active' => true,
                            'priority' => 10,
                            'options' => json_encode([
                                'created_by_patch' => 'V5.67.0b',
                                'created_by_user_id' => $userId,
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'notes' => 'Mapeo contable de nómina creado por V5.67.0b.',
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            }

            if (Schema::hasTable('accounting_journal_mappings')) {
                DB::table('accounting_journal_mappings')->updateOrInsert(
                    [
                        'company_id' => $companyId,
                        'module' => 'payroll',
                        'operation_type' => 'payroll_run',
                    ],
                    [
                        'journal_id' => $journal->id,
                        'is_active' => true,
                        'options' => json_encode([
                            'created_by_patch' => 'V5.67.0b',
                            'created_by_user_id' => $userId,
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'notes' => 'Diario para pólizas de nómina creado por V5.67.0b.',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            $this->audit(
                $companyId,
                'payroll_accounting_setup',
                0,
                null,
                'setup_payroll_accounting_defaults',
                'success',
                'Mapeos contables de nómina preparados.',
                ['company_id' => $companyId],
                [
                    'journal_id' => $journal->id,
                    'accounts' => collect($accounts)->map(fn ($a) => [
                        'id' => $a->id,
                        'code' => $a->code,
                        'name' => $a->name,
                    ])->all(),
                ],
                $userId
            );

            return [
                'journal' => $journal,
                'accounts' => $accounts,
            ];
        });
    }

    public function summary(int $companyId, int $payrollRunId): array
    {
        $run = $this->findPayrollRun($companyId, $payrollRunId);

        $entries = DB::table('accounting_entries')
            ->where('company_id', $companyId)
            ->whereIn('source_type', [self::SOURCE_TYPE, self::REVERSAL_SOURCE_TYPE])
            ->where('source_id', $payrollRunId)
            ->orderBy('id')
            ->get();

        return [
            'run' => $run,
            'dry_run' => $this->dryRun($companyId, $payrollRunId),
            'entries' => $entries,
        ];
    }

    public function dryRun(int $companyId, int $payrollRunId): array
    {
        $run = $this->findPayrollRun($companyId, $payrollRunId);
        $this->guardRunCanBePosted($run);

        $currency = (string) ($run->currency ?: 'MXN');
        $label = $this->buildRunLabel($run);

        $amounts = $this->calculateAmounts($companyId, $payrollRunId, $run);

        $lines = [];
        $lineNumber = 1;

        if ($amounts['gross'] > 0) {
            $account = $this->resolvePayrollAccount($companyId, 'payroll_expense');

            $lines[] = [
                'line_number' => $lineNumber++,
                'mapping_key' => 'payroll_expense',
                'account_id' => $account->id,
                'account_code' => $account->code,
                'account_name' => $account->name,
                'label' => 'Cargo a sueldos y salarios - ' . $label,
                'debit' => $amounts['gross'],
                'credit' => 0.0,
                'currency' => $currency,
            ];
        }

        if ($amounts['tax_deductions'] > 0) {
            $account = $this->resolvePayrollAccount($companyId, 'payroll_tax_withholding_payable');

            $lines[] = [
                'line_number' => $lineNumber++,
                'mapping_key' => 'payroll_tax_withholding_payable',
                'account_id' => $account->id,
                'account_code' => $account->code,
                'account_name' => $account->name,
                'label' => 'Abono a retenciones de nómina - ' . $label,
                'debit' => 0.0,
                'credit' => $amounts['tax_deductions'],
                'currency' => $currency,
            ];
        }

        if ($amounts['other_deductions'] > 0) {
            $account = $this->resolvePayrollAccount($companyId, 'payroll_deduction_payable');

            $lines[] = [
                'line_number' => $lineNumber++,
                'mapping_key' => 'payroll_deduction_payable',
                'account_id' => $account->id,
                'account_code' => $account->code,
                'account_name' => $account->name,
                'label' => 'Abono a deducciones de nómina - ' . $label,
                'debit' => 0.0,
                'credit' => $amounts['other_deductions'],
                'currency' => $currency,
            ];
        }

        if ($amounts['net'] > 0) {
            $account = $this->resolvePayrollAccount($companyId, 'payroll_payable');

            $lines[] = [
                'line_number' => $lineNumber++,
                'mapping_key' => 'payroll_payable',
                'account_id' => $account->id,
                'account_code' => $account->code,
                'account_name' => $account->name,
                'label' => 'Abono a sueldos por pagar - ' . $label,
                'debit' => 0.0,
                'credit' => $amounts['net'],
                'currency' => $currency,
            ];
        }

        $totalDebit = round(array_sum(array_map(fn ($line) => (float) $line['debit'], $lines)), 6);
        $totalCredit = round(array_sum(array_map(fn ($line) => (float) $line['credit'], $lines)), 6);

        if (abs($totalDebit - $totalCredit) > 0.0001) {
            throw new RuntimeException('El dry-run de nómina no cuadra: debe=' . $totalDebit . ', haber=' . $totalCredit . '.');
        }

        return [
            'company_id' => $companyId,
            'payroll_run_id' => $payrollRunId,
            'payroll_run_name' => $run->name,
            'entry_date' => $this->resolveEntryDate($run),
            'currency' => $currency,
            'amounts' => $amounts,
            'lines' => $lines,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'balanced' => abs($totalDebit - $totalCredit) <= 0.0001,
        ];
    }

    public function post(int $companyId, int $payrollRunId, ?int $userId = null): object
    {
        return DB::transaction(function () use ($companyId, $payrollRunId, $userId): object {
            $run = DB::table('payroll_runs')
                ->where('company_id', $companyId)
                ->where('id', $payrollRunId)
                ->lockForUpdate()
                ->first();

            if (! $run) {
                throw new RuntimeException('No se encontró la nómina para contabilizar.');
            }

            $this->guardRunCanBePosted($run);
            $this->setupDefaultMappings($companyId, $userId);

            $existing = DB::table('accounting_entries')
                ->where('company_id', $companyId)
                ->where('source_type', self::SOURCE_TYPE)
                ->where('source_id', $payrollRunId)
                ->whereIn('status', ['draft', 'posted'])
                ->first();

            if ($existing) {
                return $existing;
            }

            $dryRun = $this->dryRun($companyId, $payrollRunId);
            $journal = $this->resolveJournal($companyId);
            $label = $this->buildRunLabel($run);
            $entryNumber = $this->buildEntryNumber($journal, 'NOM', $payrollRunId);

            $entryId = DB::table('accounting_entries')->insertGetId([
                'company_id' => $companyId,
                'journal_id' => $journal?->id,
                'entry_number' => $entryNumber,
                'entry_date' => $dryRun['entry_date'],
                'status' => 'posted',
                'source_type' => self::SOURCE_TYPE,
                'source_id' => $payrollRunId,
                'source_label' => $label,
                'currency' => $dryRun['currency'],
                'total_debit' => $dryRun['total_debit'],
                'total_credit' => $dryRun['total_credit'],
                'posted_at' => now(),
                'cancelled_at' => null,
                'cancelled_by_entry_id' => null,
                'created_by_user_id' => $userId,
                'posted_by_user_id' => $userId,
                'notes' => 'Póliza automática de nómina generada por V5.67.0b.',
                'metadata' => json_encode([
                    'created_by_patch' => 'V5.67.0b',
                    'payroll_run_id' => $payrollRunId,
                    'period_start' => $run->period_start ?? null,
                    'period_end' => $run->period_end ?? null,
                    'payment_date' => $run->payment_date ?? null,
                    'amounts' => $dryRun['amounts'],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($dryRun['lines'] as $line) {
                DB::table('accounting_entry_lines')->insert([
                    'company_id' => $companyId,
                    'accounting_entry_id' => $entryId,
                    'account_id' => $line['account_id'],
                    'line_number' => $line['line_number'],
                    'label' => $line['label'],
                    'partner_contact_id' => null,
                    'debit' => round((float) $line['debit'], 6),
                    'credit' => round((float) $line['credit'], 6),
                    'currency' => $line['currency'],
                    'source_type' => self::SOURCE_TYPE,
                    'source_id' => $payrollRunId,
                    'metadata' => json_encode([
                        'created_by_patch' => 'V5.67.0b',
                        'mapping_key' => $line['mapping_key'],
                        'account_code' => $line['account_code'],
                        'account_name' => $line['account_name'],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->assertEntryBalances($entryId);

            $this->audit(
                $companyId,
                self::SOURCE_TYPE,
                $payrollRunId,
                $entryId,
                'post_payroll_run',
                'success',
                'Nómina contabilizada correctamente.',
                ['dry_run' => $dryRun],
                [
                    'entry_id' => $entryId,
                    'entry_number' => $entryNumber,
                ],
                $userId
            );

            return DB::table('accounting_entries')->where('id', $entryId)->first();
        });
    }

    public function reverse(int $companyId, int $payrollRunId, string $reason, ?int $userId = null): ?object
    {
        return DB::transaction(function () use ($companyId, $payrollRunId, $reason, $userId): ?object {
            $original = DB::table('accounting_entries')
                ->where('company_id', $companyId)
                ->where('source_type', self::SOURCE_TYPE)
                ->where('source_id', $payrollRunId)
                ->where('status', 'posted')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $original) {
                $cancelled = DB::table('accounting_entries')
                    ->where('company_id', $companyId)
                    ->where('source_type', self::SOURCE_TYPE)
                    ->where('source_id', $payrollRunId)
                    ->where('status', 'cancelled')
                    ->whereNotNull('cancelled_by_entry_id')
                    ->orderByDesc('id')
                    ->first();

                if ($cancelled) {
                    return DB::table('accounting_entries')->where('id', $cancelled->cancelled_by_entry_id)->first();
                }

                return null;
            }

            $existingReversal = DB::table('accounting_entries')
                ->where('company_id', $companyId)
                ->where('source_type', self::REVERSAL_SOURCE_TYPE)
                ->where('source_id', $payrollRunId)
                ->whereIn('status', ['draft', 'posted'])
                ->orderByDesc('id')
                ->first();

            if ($existingReversal) {
                DB::table('accounting_entries')
                    ->where('id', $original->id)
                    ->update([
                        'status' => 'cancelled',
                        'cancelled_at' => now(),
                        'cancelled_by_entry_id' => $existingReversal->id,
                        'updated_at' => now(),
                    ]);

                return $existingReversal;
            }

            $originalLines = DB::table('accounting_entry_lines')
                ->where('company_id', $companyId)
                ->where('accounting_entry_id', $original->id)
                ->orderBy('line_number')
                ->get();

            if ($originalLines->isEmpty()) {
                throw new RuntimeException('La póliza original no tiene líneas para reversar.');
            }

            $entryNumber = $this->buildEntryNumber(
                DB::table('accounting_journals')->where('id', $original->journal_id)->first(),
                'NOM-REV',
                $payrollRunId
            );

            $reversalId = DB::table('accounting_entries')->insertGetId([
                'company_id' => $companyId,
                'journal_id' => $original->journal_id,
                'entry_number' => $entryNumber,
                'entry_date' => now()->toDateString(),
                'status' => 'posted',
                'source_type' => self::REVERSAL_SOURCE_TYPE,
                'source_id' => $payrollRunId,
                'source_label' => 'Reversa ' . ($original->source_label ?? ('nómina #' . $payrollRunId)),
                'currency' => $original->currency ?: 'MXN',
                'total_debit' => $original->total_credit,
                'total_credit' => $original->total_debit,
                'posted_at' => now(),
                'cancelled_at' => null,
                'cancelled_by_entry_id' => null,
                'created_by_user_id' => $userId,
                'posted_by_user_id' => $userId,
                'notes' => 'Reversa de póliza de nómina. Motivo: ' . $reason,
                'metadata' => json_encode([
                    'created_by_patch' => 'V5.67.0b',
                    'reverses_accounting_entry_id' => $original->id,
                    'reverses_entry_number' => $original->entry_number,
                    'payroll_run_id' => $payrollRunId,
                    'reason' => $reason,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($originalLines as $line) {
                DB::table('accounting_entry_lines')->insert([
                    'company_id' => $companyId,
                    'accounting_entry_id' => $reversalId,
                    'account_id' => $line->account_id,
                    'line_number' => $line->line_number,
                    'label' => 'Reversa - ' . $line->label,
                    'partner_contact_id' => $line->partner_contact_id,
                    'debit' => round((float) $line->credit, 6),
                    'credit' => round((float) $line->debit, 6),
                    'currency' => $line->currency ?: ($original->currency ?: 'MXN'),
                    'source_type' => self::REVERSAL_SOURCE_TYPE,
                    'source_id' => $payrollRunId,
                    'metadata' => json_encode([
                        'created_by_patch' => 'V5.67.0b',
                        'reverses_line_id' => $line->id,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->assertEntryBalances($reversalId);

            DB::table('accounting_entries')
                ->where('id', $original->id)
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_by_entry_id' => $reversalId,
                    'updated_at' => now(),
                ]);

            $this->audit(
                $companyId,
                self::REVERSAL_SOURCE_TYPE,
                $payrollRunId,
                $reversalId,
                'reverse_payroll_run',
                'success',
                'Póliza de nómina reversada correctamente.',
                [
                    'original_entry_id' => $original->id,
                    'reason' => $reason,
                ],
                [
                    'reversal_entry_id' => $reversalId,
                    'reversal_entry_number' => $entryNumber,
                ],
                $userId
            );

            return DB::table('accounting_entries')->where('id', $reversalId)->first();
        });
    }

    protected function calculateAmounts(int $companyId, int $payrollRunId, object $run): array
    {
        $concepts = Schema::hasTable('payroll_run_line_concepts')
            ? DB::table('payroll_run_line_concepts')
                ->where('company_id', $companyId)
                ->where('payroll_run_id', $payrollRunId)
                ->get()
            : collect();

        $gross = 0.0;
        $taxDeductions = 0.0;
        $otherDeductions = 0.0;

        foreach ($concepts as $concept) {
            $amount = round((float) ($concept->amount ?? 0), 6);

            if ($amount <= 0) {
                continue;
            }

            if ((string) ($concept->type ?? '') === 'perception') {
                $gross += $amount;
                continue;
            }

            if ((string) ($concept->type ?? '') === 'deduction') {
                if ($this->isTaxDeduction($concept)) {
                    $taxDeductions += $amount;
                } else {
                    $otherDeductions += $amount;
                }
            }
        }

        if ($gross <= 0) {
            $gross = round((float) ($run->gross_total ?? $run->perceptions_total ?? 0), 6);
        }

        $deductions = round($taxDeductions + $otherDeductions, 6);

        if ($deductions <= 0) {
            $deductions = round((float) ($run->deductions_total ?? 0), 6);
            $otherDeductions = $deductions;
        }

        $net = round($gross - $deductions, 6);

        if (isset($run->net_total) && abs($net - (float) $run->net_total) <= 0.05) {
            $net = round((float) $run->net_total, 6);
        }

        return [
            'gross' => round($gross, 6),
            'tax_deductions' => round($taxDeductions, 6),
            'other_deductions' => round($otherDeductions, 6),
            'deductions' => round($deductions, 6),
            'net' => round($net, 6),
        ];
    }

    protected function isTaxDeduction(object $concept): bool
    {
        $haystack = strtolower(implode(' ', [
            (string) ($concept->code ?? ''),
            (string) ($concept->name ?? ''),
            (string) ($concept->category ?? ''),
            (string) ($concept->sat_key ?? ''),
        ]));

        foreach (['isr', 'imss', 'seguro social', 'retencion', 'retención', 'impuesto', 'subsidio'] as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function findPayrollRun(int $companyId, int $payrollRunId): object
    {
        $run = DB::table('payroll_runs')
            ->where('company_id', $companyId)
            ->where('id', $payrollRunId)
            ->first();

        if (! $run) {
            throw new RuntimeException('No se encontró la nómina.');
        }

        return $run;
    }

    protected function guardRunCanBePosted(object $run): void
    {
        $status = (string) ($run->status ?? '');
        $approval = (string) ($run->approval_status ?? '');

        if (! in_array($status, ['closed', 'approved', 'paid'], true) && $approval !== 'approved') {
            throw new RuntimeException('Solo se pueden contabilizar nóminas cerradas o aprobadas.');
        }

        $gross = round((float) ($run->gross_total ?? $run->perceptions_total ?? 0), 6);
        $net = round((float) ($run->net_total ?? 0), 6);

        if ($gross <= 0 && $net <= 0) {
            throw new RuntimeException('La nómina no tiene importes para contabilizar.');
        }
    }

    protected function resolveEntryDate(object $run): string
    {
        return (string) ($run->payment_date ?: $run->period_end ?: now()->toDateString());
    }

    protected function buildRunLabel(object $run): string
    {
        return 'Nómina ' . ($run->name ?? ('#' . $run->id));
    }

    protected function resolveJournal(int $companyId): ?object
    {
        if (Schema::hasTable('accounting_journal_mappings')) {
            $mapped = DB::table('accounting_journal_mappings as m')
                ->join('accounting_journals as j', 'j.id', '=', 'm.journal_id')
                ->where('m.company_id', $companyId)
                ->where('m.module', 'payroll')
                ->where('m.operation_type', 'payroll_run')
                ->where('m.is_active', true)
                ->where('j.company_id', $companyId)
                ->where('j.is_active', true)
                ->first(['j.*']);

            if ($mapped) {
                return $mapped;
            }
        }

        return DB::table('accounting_journals')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('code', 'GEN')
                    ->orWhere('type', 'general');
            })
            ->orderByRaw("case when code = 'GEN' then 0 else 1 end")
            ->first();
    }

    protected function resolveOrCreateGeneralJournal(int $companyId): object
    {
        $journal = $this->resolveJournal($companyId);

        if ($journal) {
            return $journal;
        }

        $id = DB::table('accounting_journals')->insertGetId([
            'company_id' => $companyId,
            'code' => 'GEN',
            'name' => 'Diario General',
            'type' => 'general',
            'default_account_id' => null,
            'is_active' => true,
            'description' => 'Diario general creado por V5.67.0b para nómina.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('accounting_journals')->where('id', $id)->first();
    }

    protected function resolvePayrollAccount(int $companyId, string $mappingKey): object
    {
        if (Schema::hasTable('accounting_mappings')) {
            $mapped = DB::table('accounting_mappings as m')
                ->join('accounting_accounts as a', 'a.id', '=', 'm.account_id')
                ->where('m.company_id', $companyId)
                ->where('m.module', 'payroll')
                ->where('m.operation_type', 'payroll_run')
                ->where('m.mapping_key', $mappingKey)
                ->where('m.is_active', true)
                ->where('a.company_id', $companyId)
                ->where('a.is_active', true)
                ->orderBy('m.priority')
                ->first(['a.*']);

            if ($mapped) {
                return $mapped;
            }
        }

        $account = DB::table('accounting_accounts')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('account_usage', $mappingKey)
            ->orderBy('code')
            ->first();

        if ($account) {
            return $account;
        }

        throw new RuntimeException('No se encontró cuenta contable de nómina para: ' . $mappingKey . '. Ejecuta setup-defaults.');
    }

    protected function resolveOrCreateAccount(
        int $companyId,
        string $code,
        string $name,
        string $type,
        string $normalBalance,
        string $usage,
        string $description
    ): object {
        $existing = DB::table('accounting_accounts')
            ->where('company_id', $companyId)
            ->where(function ($q) use ($code, $usage) {
                $q->where('code', $code)
                    ->orWhere('account_usage', $usage);
            })
            ->orderByRaw("case when account_usage = ? then 0 when code = ? then 1 else 2 end", [$usage, $code])
            ->first();

        if ($existing) {
            return $existing;
        }

        $payload = [
            'company_id' => $companyId,
            'parent_id' => null,
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'normal_balance' => $normalBalance,
            'is_active' => true,
            'is_system' => true,
            'description' => $description,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('accounting_accounts', 'sat_grouping_code')) {
            $payload['sat_grouping_code'] = str_starts_with($code, '601') ? '601.01' : '210.01';
        }

        if (Schema::hasColumn('accounting_accounts', 'account_usage')) {
            $payload['account_usage'] = $usage;
        }

        if (Schema::hasColumn('accounting_accounts', 'allow_manual_entries')) {
            $payload['allow_manual_entries'] = true;
        }

        $id = DB::table('accounting_accounts')->insertGetId($payload);

        return DB::table('accounting_accounts')->where('id', $id)->first();
    }

    protected function buildEntryNumber(?object $journal, string $prefix, int $id): string
    {
        $journalCode = $journal?->code ?: 'GEN';
        $base = sprintf('%s-%s-%08d', $journalCode, $prefix, $id);
        $companyId = (int) ($journal->company_id ?? 0);

        if ($companyId <= 0) {
            return $base;
        }

        $candidate = $base;

        for ($i = 1; $i <= 99; $i++) {
            if (! DB::table('accounting_entries')
                ->where('company_id', $companyId)
                ->where('entry_number', $candidate)
                ->exists()) {
                return $candidate;
            }

            $candidate = $base . '-' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
        }

        throw new RuntimeException('No se pudo generar folio contable único para nómina: ' . $base);
    }

    protected function assertEntryBalances(int $entryId): void
    {
        $debit = round((float) DB::table('accounting_entry_lines')
            ->where('accounting_entry_id', $entryId)
            ->sum('debit'), 6);

        $credit = round((float) DB::table('accounting_entry_lines')
            ->where('accounting_entry_id', $entryId)
            ->sum('credit'), 6);

        if (abs($debit - $credit) > 0.0001) {
            throw new RuntimeException('La póliza de nómina no cuadra: debe=' . $debit . ', haber=' . $credit . '.');
        }

        DB::table('accounting_entries')
            ->where('id', $entryId)
            ->update([
                'total_debit' => $debit,
                'total_credit' => $credit,
                'updated_at' => now(),
            ]);
    }

    protected function audit(
        int $companyId,
        string $sourceType,
        int $sourceId,
        ?int $entryId,
        string $event,
        string $status,
        string $message,
        array $requestMeta,
        array $responseMeta,
        ?int $userId
    ): void {
        if (! Schema::hasTable('accounting_posting_audits')) {
            return;
        }

        DB::table('accounting_posting_audits')->insert([
            'company_id' => $companyId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'accounting_entry_id' => $entryId,
            'event' => $event,
            'status' => $status,
            'message' => $message,
            'request_meta' => json_encode($requestMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'response_meta' => json_encode($responseMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_by_user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
