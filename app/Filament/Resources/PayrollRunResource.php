<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayrollRunResource\Pages;
use App\Filament\Resources\PayrollRunResource\RelationManagers\LinesRelationManager;
use App\Models\PayrollRun;
use App\Support\PayrollRunCalculator;
use App\Support\PayrollRunExportService;
use App\Support\PayrollRunApprovalWorkflow;
use App\Support\PayrollRunCloseService;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollRunResource extends Resource
{
    protected static ?string $model = PayrollRun::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'RRHH';

    protected static ?string $navigationLabel = 'Pre-nómina';

    protected static ?string $modelLabel = 'pre-nómina';

    protected static ?string $pluralModelLabel = 'pre-nómina';

    protected static ?int $navigationSort = 23;

    protected static bool $isScopedToTenant = false;

    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static function bexiaCanPayrollPermission(string $permission): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ((bool) ($user->is_system_admin ?? false)) {
            return true;
        }

        if (($user->email ?? null) === 'admin@bexiaerp.com') {
            return true;
        }

        return $user->can($permission) || $user->can('company.update');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::bexiaCanPayrollPermission('nomina.prenomina.ver');
    }

    public static function canViewAny(): bool
    {
        return static::bexiaCanPayrollPermission('nomina.prenomina.ver');
    }

    public static function canView(Model $record): bool
    {
        return static::bexiaCanPayrollPermission('nomina.prenomina.ver');
    }

    public static function canCreate(): bool
    {
        return static::bexiaCanPayrollPermission('nomina.prenomina.crear');
    }

    public static function canEdit(Model $record): bool
    {
        return static::bexiaCanPayrollPermission('nomina.prenomina.editar');
    }

    public static function canDelete(Model $record): bool
    {
        return static::bexiaCanPayrollPermission('nomina.prenomina.eliminar');
    }

    public static function canDeleteAny(): bool
    {
        return static::bexiaCanPayrollPermission('nomina.prenomina.eliminar');
    }


    public static function exportExcel(PayrollRun $record): BinaryFileResponse
    {
        $tmp = tempnam(sys_get_temp_dir(), 'bexia_payroll_run_');

        if ($tmp === false) {
            throw new \RuntimeException('No se pudo crear archivo temporal para Excel.');
        }

        $path = $tmp . '.xlsx';
        @rename($tmp, $path);

        PayrollRunExportService::writeExcel($path, $record);

        return response()
            ->download($path, static::exportFilename($record, 'xlsx'), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    public static function exportPdf(PayrollRun $record): StreamedResponse
    {
        if (! app()->bound('dompdf.wrapper')) {
            throw new \RuntimeException('No hay motor PDF instalado (barryvdh/laravel-dompdf).');
        }

        $data = PayrollRunExportService::data($record);

        $pdf = app('dompdf.wrapper')
            ->loadView('reports.hr.payroll-run-pdf', $data)
            ->setPaper('letter', 'landscape');

        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf->output();
        }, static::exportFilename($record, 'pdf'), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    protected static function exportFilename(PayrollRun $record, string $extension): string
    {
        $from = $record->period_start?->format('Ymd') ?: 'sin_inicio';
        $to = $record->period_end?->format('Ymd') ?: 'sin_fin';
        $name = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $record->name) ?: 'prenomina';

        return "prenomina_{$record->id}_{$from}_{$to}_{$name}.{$extension}";
    }

    protected static function updateStatus(PayrollRun $record, string $status): void
    {
        $record->forceFill([
            'status' => $status,
            'updated_by_user_id' => auth()->id(),
        ])->save();
    }


    public static function bexiaCanPayrollAccounting(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ((bool) ($user->is_system_admin ?? false)) {
            return true;
        }

        if (($user->email ?? null) === 'admin@bexiaerp.com') {
            return true;
        }

        return $user->can('nomina.prenomina.cerrar')
            || $user->can('accounting.create')
            || $user->can('accounting.post')
            || $user->can('accounting.view')
            || $user->can('company.update');
    }

    public static function payrollAccountingActiveEntryId(PayrollRun $record): ?int
    {
        $entry = \Illuminate\Support\Facades\DB::table('accounting_entries')
            ->where('company_id', (int) $record->company_id)
            ->where('source_type', 'payroll_run')
            ->where('source_id', (int) $record->id)
            ->whereIn('status', ['draft', 'posted'])
            ->orderByDesc('id')
            ->first(['id']);

        return $entry ? (int) $entry->id : null;
    }

    public static function payrollAccountingAnyEntryId(PayrollRun $record): ?int
    {
        $entry = \Illuminate\Support\Facades\DB::table('accounting_entries')
            ->where('company_id', (int) $record->company_id)
            ->whereIn('source_type', ['payroll_run', 'payroll_run_reversal'])
            ->where('source_id', (int) $record->id)
            ->orderByDesc('id')
            ->first(['id']);

        return $entry ? (int) $entry->id : null;
    }

    public static function payrollAccountingStatusLabel(PayrollRun $record): string
    {
        $active = \Illuminate\Support\Facades\DB::table('accounting_entries')
            ->where('company_id', (int) $record->company_id)
            ->where('source_type', 'payroll_run')
            ->where('source_id', (int) $record->id)
            ->whereIn('status', ['draft', 'posted'])
            ->orderByDesc('id')
            ->first(['status']);

        if ($active) {
            return (string) $active->status === 'posted' ? 'Contabilizada' : 'Borrador contable';
        }

        $reversal = \Illuminate\Support\Facades\DB::table('accounting_entries')
            ->where('company_id', (int) $record->company_id)
            ->where('source_type', 'payroll_run_reversal')
            ->where('source_id', (int) $record->id)
            ->where('status', 'posted')
            ->exists();

        if ($reversal) {
            return 'Reversada';
        }

        return 'Pendiente';
    }

    public static function payrollAccountingStatusColor(PayrollRun $record): string
    {
        return match (static::payrollAccountingStatusLabel($record)) {
            'Contabilizada' => 'success',
            'Borrador contable' => 'warning',
            'Reversada' => 'gray',
            default => 'danger',
        };
    }

    public static function payrollAccountingEntryUrl(PayrollRun $record): ?string
    {
        $entryId = static::payrollAccountingActiveEntryId($record)
            ?: static::payrollAccountingAnyEntryId($record);

        if (! $entryId) {
            return null;
        }

        return \App\Filament\Resources\AccountingEntryResource::getUrl('view', ['record' => $entryId]);
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = Filament::getTenant()?->getKey();

        return parent::getEloquentQuery()
            ->withCount('lines')
            ->when($tenantId, fn (Builder $query) => $query->where('company_id', $tenantId));
    }


    /*
     * BEXIA_PAYROLL_RUN_RESOURCE_RESPONSIVE_V5_79_38C
     * Visual-only responsive marker.
     */

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Periodo de pre-nómina')
                    ->extraAttributes(['class' => 'bexia-payroll-run-section bexia-payroll-run-section-main'])
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->extraAttributes(['class' => 'bexia-payroll-run-field bexia-payroll-run-field-name'])
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('period_type')
                            ->extraAttributes(['class' => 'bexia-payroll-run-field bexia-payroll-run-field-cycle-kind'])
                            ->label('Periodicidad')
                            ->options(PayrollRun::periodTypeOptions())
                            ->default('quincenal')
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->extraAttributes(['class' => 'bexia-payroll-run-field bexia-payroll-run-field-state'])
                            ->label('Estado')
                            ->options(PayrollRun::statusOptions())
                            ->default('draft')
                            ->disabled()
                            ->dehydrated(true),

                        Forms\Components\DatePicker::make('period_start')
                            ->extraAttributes(['class' => 'bexia-payroll-run-field bexia-payroll-run-field-date-from'])
                            ->label('Inicio')
                            ->native(false)
                            ->default(now()->startOfMonth())
                            ->required(),

                        Forms\Components\DatePicker::make('period_end')
                            ->extraAttributes(['class' => 'bexia-payroll-run-field bexia-payroll-run-field-date-to'])
                            ->label('Fin')
                            ->native(false)
                            ->default(now()->endOfMonth())
                            ->required(),

                        Forms\Components\DatePicker::make('payment_date')
                            ->extraAttributes(['class' => 'bexia-payroll-run-field bexia-payroll-run-field-paydate'])
                            ->label('Fecha de pago')
                            ->native(false),

                        Forms\Components\TextInput::make('currency')
                            ->extraAttributes(['class' => 'bexia-payroll-run-field bexia-payroll-run-field-currency'])
                            ->label('Moneda')
                            ->default('MXN')
                            ->maxLength(3),

                        Forms\Components\Textarea::make('notes')
                            ->extraAttributes(['class' => 'bexia-payroll-run-field bexia-payroll-run-field-notes'])
                            ->label('Notas')
                            ->columnSpanFull()
                            ->rows(3),
                    ]),

                Forms\Components\Section::make('Totales')
                    ->extraAttributes(['class' => 'bexia-payroll-run-section bexia-payroll-run-section-money'])
                    ->columns(4)
                    ->schema([
                        Forms\Components\TextInput::make('employees_count')->label('Empleados')->disabled()
                            ->extraAttributes(['class' => 'bexia-payroll-run-field bexia-payroll-run-field-employees']),
                        Forms\Components\TextInput::make('base_total')->label('Sueldo base')->disabled()->prefix('$')
                            ->extraAttributes(['class' => 'bexia-payroll-run-field bexia-payroll-run-field-base-money']),
                        Forms\Components\TextInput::make('overtime_total')->label('Horas extra')->disabled()->prefix('$')
                            ->extraAttributes(['class' => 'bexia-payroll-run-field bexia-payroll-run-field-overtime-money']),
                        Forms\Components\TextInput::make('perceptions_total')->label('Percepciones')->disabled()->prefix('$')
                            ->extraAttributes(['class' => 'bexia-payroll-run-field bexia-payroll-run-field-perceptions-money']),
                        Forms\Components\TextInput::make('deductions_total')->label('Deducciones')->disabled()->prefix('$')
                            ->extraAttributes(['class' => 'bexia-payroll-run-field bexia-payroll-run-field-deductions-money']),
                        Forms\Components\TextInput::make('gross_total')->label('Bruto')->disabled()->prefix('$')
                            ->extraAttributes(['class' => 'bexia-payroll-run-field bexia-payroll-run-field-bruto-money']),
                        Forms\Components\TextInput::make('net_total')->label('Neto')->disabled()->prefix('$')
                            ->extraAttributes(['class' => 'bexia-payroll-run-field bexia-payroll-run-field-neto-money']),
                        Forms\Components\TextInput::make('calculated_at')->label('Calculada')->disabled()
                            ->extraAttributes(['class' => 'bexia-payroll-run-field bexia-payroll-run-field-computed-at']),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('payroll_cfdi_status')
                    ->extraHeaderAttributes(['class' => 'bexia-payroll-run-col-cfdi-state'])
                    ->extraCellAttributes(['class' => 'bexia-payroll-run-col-cfdi-state'])
                    ->label('CFDI nomina')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'drafts_prepared' => 'Borradores listos',
                        'drafts_error' => 'Error CFDI',
                        'validated' => 'Validado',
                        'stamped' => 'Timbrado',
                        'partial_stamped' => 'Parcialmente timbrada',
                        'partial_validated' => 'Parcialmente validada',
                        'partial_error' => 'Con errores parciales',
                        'cancelled' => 'Cancelada',
                        'stamping_error' => 'Error de timbrado',
                        'xml_generated' => 'XML generado',
                        null, '' => 'Sin preparar',
                        default => $state,
                    })
                    ->badge()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('payroll_cfdi_ready_lines_count')
                    ->extraHeaderAttributes(['class' => 'bexia-payroll-run-col-cfdi-ready'])
                    ->extraCellAttributes(['class' => 'bexia-payroll-run-col-cfdi-ready'])
                    ->label('CFDI listos')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('payroll_cfdi_error_lines_count')
                    ->extraHeaderAttributes(['class' => 'bexia-payroll-run-col-cfdi-errors'])
                    ->extraCellAttributes(['class' => 'bexia-payroll-run-col-cfdi-errors'])
                    ->label('Errores CFDI')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('name')
                    ->extraHeaderAttributes(['class' => 'bexia-payroll-run-col-name bexia-payroll-run-col-primary'])
                    ->extraCellAttributes(['class' => 'bexia-payroll-run-col-name bexia-payroll-run-col-primary'])
                    ->label('Pre-nómina')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('period_type')
                    ->extraHeaderAttributes(['class' => 'bexia-payroll-run-col-cycle'])
                    ->extraCellAttributes(['class' => 'bexia-payroll-run-col-cycle'])
                    ->label('Periodicidad')
                    ->formatStateUsing(fn (?string $state): string => PayrollRun::periodTypeOptions()[$state] ?? ($state ?: '-'))
                    ->badge(),

                Tables\Columns\TextColumn::make('period_start')
                    ->extraHeaderAttributes(['class' => 'bexia-payroll-run-col-date-from'])
                    ->extraCellAttributes(['class' => 'bexia-payroll-run-col-date-from'])
                    ->label('Inicio')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('period_end')
                    ->extraHeaderAttributes(['class' => 'bexia-payroll-run-col-date-to'])
                    ->extraCellAttributes(['class' => 'bexia-payroll-run-col-date-to'])
                    ->label('Fin')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->extraHeaderAttributes(['class' => 'bexia-payroll-run-col-state bexia-payroll-run-col-badge'])
                    ->extraCellAttributes(['class' => 'bexia-payroll-run-col-state bexia-payroll-run-col-badge'])
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => PayrollRun::statusOptions()[$state] ?? ($state ?: '-')),

                Tables\Columns\TextColumn::make('employees_count')
                    ->extraHeaderAttributes(['class' => 'bexia-payroll-run-col-employees'])
                    ->extraCellAttributes(['class' => 'bexia-payroll-run-col-employees'])
                    ->label('Empleados')
                    ->sortable(),

                Tables\Columns\TextColumn::make('gross_total')
                    ->extraHeaderAttributes(['class' => 'bexia-payroll-run-col-bruto-money bexia-payroll-run-col-money'])
                    ->extraCellAttributes(['class' => 'bexia-payroll-run-col-bruto-money bexia-payroll-run-col-money'])
                    ->label('Bruto')
                    ->money('MXN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('deductions_total')
                    ->extraHeaderAttributes(['class' => 'bexia-payroll-run-col-deductions-money bexia-payroll-run-col-money'])
                    ->extraCellAttributes(['class' => 'bexia-payroll-run-col-deductions-money bexia-payroll-run-col-money'])
                    ->label('Deducciones')
                    ->money('MXN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('net_total')
                    ->extraHeaderAttributes(['class' => 'bexia-payroll-run-col-neto-money bexia-payroll-run-col-money'])
                    ->extraCellAttributes(['class' => 'bexia-payroll-run-col-neto-money bexia-payroll-run-col-money'])
                    ->label('Neto')
                    ->money('MXN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('payroll_accounting_status')
                    ->extraHeaderAttributes(['class' => 'bexia-payroll-run-col-accounting-state'])
                    ->extraCellAttributes(['class' => 'bexia-payroll-run-col-accounting-state'])
                    ->label('Contabilidad')
                    ->state(fn (PayrollRun $record): string => static::payrollAccountingStatusLabel($record))
                    ->badge()
                    ->color(fn (PayrollRun $record): string => static::payrollAccountingStatusColor($record))
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('prepare_payroll_cfdi_receipts')
                    ->label('Preparar CFDI')
                    ->icon('heroicon-o-document-plus')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Preparar recibos CFDI nomina')
                    ->modalDescription('Creara recibos CFDI de nomina en borrador para esta corrida cerrada. No timbra, no genera XML y no envia datos al PAC/SAT.')
                    ->visible(fn ($record): bool => (bool) ($record->is_locked ?? false) && filled($record->closed_at))
                    ->action(function ($record): void {
                        $result = app(\App\Support\PayrollCfdi\PayrollCfdiReceiptPreparationService::class)->prepare(
                            companyId: (int) $record->company_id,
                            payrollRunId: (int) $record->id,
                            userId: auth()->id(),
                            force: false,
                        );

                        if (! ($result['success'] ?? false)) {
                            \Filament\Notifications\Notification::make()
                                ->title('No se pudieron preparar los CFDI')
                                ->body(collect($result['errors'] ?? [])->take(5)->implode(PHP_EOL) ?: ($result['message'] ?? 'Revisa los datos fiscales de la nomina.'))
                                ->danger()
                                ->send();

                            return;
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Recibos CFDI preparados')
                            ->body('Creados: ' . ($result['created'] ?? 0) . '. Actualizados: ' . ($result['updated'] ?? 0) . '. Omitidos: ' . ($result['skipped'] ?? 0) . '.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('prepare_payroll_cfdi_xml_drafts')
                    ->label('Generar XML')
                    ->icon('heroicon-o-document-text')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Generar XML CFDI nomina')
                    ->modalDescription('Generara XML CFDI de nomina en borrador para los recibos de esta corrida. No timbra, no genera UUID y no envia datos al PAC/SAT.')
                    ->visible(fn ($record): bool => filled($record->payroll_cfdi_status) && ((int) ($record->payroll_cfdi_ready_lines_count ?? 0) > 0))
                    ->action(function ($record): void {
                        $result = app(\App\Support\PayrollCfdi\PayrollCfdiXmlDraftService::class)->prepareForRun(
                            companyId: (int) $record->company_id,
                            payrollRunId: (int) $record->id,
                            userId: auth()->id(),
                            force: false,
                        );

                        if (! ($result['success'] ?? false)) {
                            \Filament\Notifications\Notification::make()
                                ->title('No se pudo generar XML CFDI')
                                ->body(collect($result['errors'] ?? [])->take(5)->implode(PHP_EOL) ?: ($result['message'] ?? 'Revisa los recibos CFDI de la nomina.'))
                                ->danger()
                                ->send();

                            return;
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('XML CFDI generado')
                            ->body('Generados/actualizados: ' . ($result['updated'] ?? 0) . '. Omitidos: ' . ($result['skipped'] ?? 0) . '.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('view_payroll_cfdi_receipts')
                    ->label('Ver CFDI')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->visible(fn ($record): bool => filled($record->payroll_cfdi_status) || ((int) ($record->payroll_cfdi_ready_lines_count ?? 0) > 0))
                    ->url(fn (): string => \App\Filament\Resources\PayrollCfdiReceiptResource::getUrl('index', [
                        'tenant' => \Filament\Facades\Filament::getTenant(),
                    ])),

                Tables\Actions\Action::make('calculate')
                    ->label('Calcular')
                    ->icon('heroicon-o-calculator')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PayrollRun $record): bool => static::bexiaCanPayrollPermission('nomina.prenomina.calcular') && in_array($record->status, ['draft', 'calculated'], true))
                    ->action(function (PayrollRun $record): void {
                        try {
                            PayrollRunCalculator::calculate($record, auth()->id());

                            Notification::make()
                                ->title('Pre-nómina calculada')
                                ->body('Se generaron las líneas y totales del periodo.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('No se pudo calcular')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('export_excel')
                    ->label('Excel')
                    ->icon('heroicon-o-table-cells')
                    ->color('success')
                    ->visible(fn (PayrollRun $record): bool => $record->lines()->exists())
                    ->action(fn (PayrollRun $record): BinaryFileResponse => static::exportExcel($record)),

                Tables\Actions\Action::make('export_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->visible(fn (PayrollRun $record): bool => $record->lines()->exists())
                    ->action(fn (PayrollRun $record): StreamedResponse => static::exportPdf($record)),

                Tables\Actions\Action::make('request_approval')
                    ->label('Solicitar aprobación')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (PayrollRun $record): bool => static::bexiaCanPayrollPermission('nomina.prenomina.solicitar_aprobacion') && $record->status === 'calculated')
                    ->action(function (PayrollRun $record): void {
                        try {
                            PayrollRunApprovalWorkflow::sendToApproval($record, auth()->id());

                            Notification::make()
                                ->title('Pre-nómina enviada a aprobación')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('No se pudo solicitar aprobación')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('approve_pending_approval')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PayrollRun $record): bool => PayrollRunApprovalWorkflow::currentUserCanActOnPendingRequest($record))
                    ->action(function (PayrollRun $record): void {
                        try {
                            PayrollRunApprovalWorkflow::approvePendingRequestForRun($record, auth()->id());

                            Notification::make()
                                ->title('Pre-nómina aprobada')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('No se pudo aprobar')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('reject_pending_approval')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->extraAttributes(['class' => 'bexia-payroll-run-field bexia-payroll-run-modal-field bexia-payroll-run-field-reason'])
                            ->label('Motivo del rechazo')
                            ->required()
                            ->rows(3),
                    ])
                    ->visible(fn (PayrollRun $record): bool => PayrollRunApprovalWorkflow::currentUserCanActOnPendingRequest($record))
                    ->action(function (PayrollRun $record, array $data): void {
                        try {
                            PayrollRunApprovalWorkflow::rejectPendingRequestForRun($record, auth()->id(), (string) ($data['reason'] ?? ''));

                            Notification::make()
                                ->title('Pre-nómina rechazada')
                                ->warning()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('No se pudo rechazar')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('close')
                    ->label('Cerrar nómina')
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->extraAttributes(['class' => 'bexia-payroll-run-field bexia-payroll-run-modal-field bexia-payroll-run-field-reason'])
                            ->label('Motivo del cierre')
                            ->required()
                            ->rows(3)
                            ->default('Cierre definitivo de nómina aprobado.'),
                    ])
                    ->visible(function (PayrollRun $record): bool {
                        $user = auth()->user();

                        if (! $user || $record->status !== 'approved') {
                            return false;
                        }

                        return (bool) ($user->is_system_admin ?? false)
                            || ($user->email ?? null) === 'admin@bexiaerp.com'
                            || static::bexiaCanPayrollPermission('nomina.prenomina.cerrar')
                            || $user->can('company.update');
                    })
                    ->action(function (PayrollRun $record, array $data): void {
                        try {
                            PayrollRunCloseService::close($record, auth()->id(), (string) ($data['reason'] ?? ''));

                            Notification::make()
                                ->title('Nómina cerrada y bloqueada')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('No se pudo cerrar la nómina')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('cancel')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (PayrollRun $record): bool => static::bexiaCanPayrollPermission('nomina.prenomina.editar') && in_array($record->status, ['draft', 'calculated'], true))
                    ->action(function (PayrollRun $record): void {
                        static::updateStatus($record, 'cancelled');

                        Notification::make()
                            ->title('Pre-nómina cancelada')
                            ->warning()
                            ->send();
                    }),


                Tables\Actions\Action::make('setup_payroll_accounting_defaults')
                    ->label('Config. contable')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Preparar configuración contable de nómina')
                    ->modalDescription('Creará o actualizará cuentas y mapeos contables por defecto para nómina. No genera póliza.')
                    ->modalSubmitActionLabel('Preparar configuración')
                    ->visible(fn (PayrollRun $record): bool => static::bexiaCanPayrollAccounting())
                    ->action(function (PayrollRun $record): void {
                        app(\App\Support\Accounting\PayrollAccountingPoster::class)->setupDefaultMappings(
                            companyId: (int) $record->company_id,
                            userId: auth()->id(),
                        );

                        Notification::make()
                            ->title('Configuración contable lista')
                            ->body('Cuentas y mapeos de nómina preparados.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('preview_payroll_accounting')
                    ->label('Resumen contable')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->color('info')
                    ->visible(fn (PayrollRun $record): bool => static::bexiaCanPayrollAccounting() && in_array((string) $record->status, ['closed', 'approved', 'paid'], true))
                    ->action(function (PayrollRun $record): void {
                        try {
                            $summary = app(\App\Support\Accounting\PayrollAccountingPoster::class)->dryRun(
                                companyId: (int) $record->company_id,
                                payrollRunId: (int) $record->id,
                            );

                            Notification::make()
                                ->title('Resumen contable de nómina')
                                ->body('Debe: $' . number_format((float) $summary['total_debit'], 2) . PHP_EOL
                                    . 'Haber: $' . number_format((float) $summary['total_credit'], 2) . PHP_EOL
                                    . 'Líneas: ' . count($summary['lines']))
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('No se pudo generar resumen')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('post_payroll_accounting')
                    ->label('Generar póliza')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Generar póliza contable de nómina')
                    ->modalDescription('Generará la póliza contable de esta nómina cerrada. No afecta CFDI, PAC ni SAT.')
                    ->modalSubmitActionLabel('Sí, generar póliza')
                    ->visible(fn (PayrollRun $record): bool => static::bexiaCanPayrollAccounting()
                        && in_array((string) $record->status, ['closed', 'approved', 'paid'], true)
                        && static::payrollAccountingActiveEntryId($record) === null)
                    ->action(function (PayrollRun $record): void {
                        try {
                            $entry = app(\App\Support\Accounting\PayrollAccountingPoster::class)->post(
                                companyId: (int) $record->company_id,
                                payrollRunId: (int) $record->id,
                                userId: auth()->id(),
                            );

                            Notification::make()
                                ->title('Póliza de nómina generada')
                                ->body('Póliza: ' . (string) ($entry->entry_number ?? ('ID ' . $entry->id)))
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('No se pudo generar póliza')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('view_payroll_accounting_entry')
                    ->label('Ver póliza')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->visible(fn (PayrollRun $record): bool => static::payrollAccountingAnyEntryId($record) !== null)
                    ->url(fn (PayrollRun $record): ?string => static::payrollAccountingEntryUrl($record))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('reverse_payroll_accounting')
                    ->label('Revertir póliza')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Revertir póliza contable de nómina')
                    ->modalDescription('Generará una póliza inversa y marcará la póliza original como cancelada.')
                    ->modalSubmitActionLabel('Sí, revertir póliza')
                    ->visible(fn (PayrollRun $record): bool => static::bexiaCanPayrollAccounting()
                        && static::payrollAccountingActiveEntryId($record) !== null)
                    ->form([
                        \Filament\Forms\Components\Textarea::make('reason')
                            ->extraAttributes(['class' => 'bexia-payroll-run-field bexia-payroll-run-modal-field bexia-payroll-run-field-reason'])
                            ->label('Motivo')
                            ->rows(3)
                            ->required()
                            ->default('Reversa contable de nómina.'),
                    ])
                    ->action(function (PayrollRun $record, array $data): void {
                        try {
                            $entry = app(\App\Support\Accounting\PayrollAccountingPoster::class)->reverse(
                                companyId: (int) $record->company_id,
                                payrollRunId: (int) $record->id,
                                reason: (string) ($data['reason'] ?? ''),
                                userId: auth()->id(),
                            );

                            Notification::make()
                                ->title('Póliza de nómina reversada')
                                ->body($entry ? ('Reversa: ' . (string) ($entry->entry_number ?? ('ID ' . $entry->id))) : 'No había póliza activa.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('No se pudo revertir póliza')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->label('Eliminar'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()->label('Eliminar seleccionadas'),
            ])
            ->defaultSort('period_start', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            LinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrollRuns::route('/'),
            'create' => Pages\CreatePayrollRun::route('/create'),
            'edit' => Pages\EditPayrollRun::route('/{record}/edit'),
        ];
    }
}
