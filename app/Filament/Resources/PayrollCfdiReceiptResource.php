<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayrollCfdiReceiptResource\Pages;
use App\Models\PayrollCfdiReceipt;
use Filament\Facades\Filament;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PayrollCfdiReceiptResource extends Resource
{
    protected static ?string $model = PayrollCfdiReceipt::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationGroup = 'Nómina';

    protected static ?string $navigationLabel = 'Recibos CFDI nómina';

    protected static ?string $modelLabel = 'recibo CFDI nómina';

    protected static ?string $pluralModelLabel = 'recibos CFDI nómina';

    protected static ?int $navigationSort = 38;

    protected static bool $isScopedToTenant = false;

    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static function bexiaCanView(): bool
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

        return $user->can('nomina.recibos_cfdi.ver')
            || $user->can('nomina.procesos.ver')
            || $user->can('nomina.conceptos.ver')
            || $user->can('company.update');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::bexiaCanView();
    }

    public static function canViewAny(): bool
    {
        return static::bexiaCanView();
    }

    public static function canView(Model $record): bool
    {
        return static::bexiaCanView();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn (?string $state): string => self::statusOptions()[$state] ?? ($state ?: 'Sin estado'))
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('payroll_run_id')
                    ->label('Nómina ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('payrollRun.name')
                    ->label('Nómina')
                    ->limit(40)
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Empleado')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('folio')
                    ->label('Folio')
                    ->searchable()
                    ->copyable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('series')
                    ->label('Serie')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('uuid')
                    ->label('UUID')
                    ->placeholder('Sin timbrar')
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('pdf_path')
                    ->label('PDF')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? 'Generado' : 'Pendiente')
                    ->badge()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('xml_path')
                    ->label('XML')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? 'Generado' : 'Pendiente')
                    ->badge()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('validated_at')
                    ->label('Validado')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('stamped_at')
                    ->label('Timbrado')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('Pendiente')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options(self::statusOptions()),
            ])
            ->actions([
                Tables\Actions\Action::make('cancel_payroll_cfdi')
                    ->label('Cancelar CFDI')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cancelar CFDI nómina')
                    ->modalDescription('Esto intentará cancelar el CFDI nómina ante el PAC/SAT. En DEV debe quedar bloqueado por seguridad. La cancelación real solo está permitida en PROD.')
                    ->modalSubmitActionLabel('Sí, intentar cancelar')
                    ->visible(fn ($record): bool => (string) $record->status === 'stamped' && filled($record->uuid))
                    ->form([
                        \Filament\Forms\Components\Select::make('reason')
                            ->label('Motivo SAT de cancelación')
                            ->options([
                                '01' => '01 - Comprobante emitido con errores con relación',
                                '02' => '02 - Comprobante emitido con errores sin relación',
                                '03' => '03 - No se llevó a cabo la operación',
                                '04' => '04 - Operación nominativa relacionada en factura global',
                            ])
                            ->default('02')
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('replacement_uuid')
                            ->label('UUID relacionado')
                            ->helperText('Solo aplica normalmente para motivo 01.')
                            ->maxLength(36),
                    ])
                    ->action(function ($record, array $data): void {
                        $result = app(\App\Support\PayrollCfdi\PayrollCfdiCancelService::class)->cancel(
                            companyId: (int) $record->company_id,
                            receiptId: (int) $record->id,
                            reason: (string) ($data['reason'] ?? '02'),
                            replacementUuid: filled($data['replacement_uuid'] ?? null) ? (string) $data['replacement_uuid'] : null,
                            userId: auth()->id(),
                        );

                        if (! ($result['success'] ?? false)) {
                            \Filament\Notifications\Notification::make()
                                ->title(($result['blocked'] ?? false) ? 'Cancelación bloqueada' : 'No se pudo cancelar')
                                ->body(collect($result['errors'] ?? [])->take(6)->implode(PHP_EOL) ?: ($result['message'] ?? 'Revisa el detalle del recibo CFDI nómina.'))
                                ->danger()
                                ->send();

                            return;
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('CFDI nómina cancelado')
                            ->body('UUID: ' . (($result['summary']['uuid'] ?? null) ?: 'N/D'))
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('generate_payroll_cfdi_pdf')
                    ->label('Generar PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->visible(fn ($record): bool => in_array((string) $record->status, ['validated', 'stamped'], true))
                    ->action(function ($record): void {
                        $result = app(\App\Support\PayrollCfdi\PayrollCfdiReceiptPdfService::class)->generate(
                            companyId: (int) $record->company_id,
                            receiptId: (int) $record->id,
                            userId: auth()->id(),
                            force: true,
                        );

                        if (! ($result['success'] ?? false)) {
                            \Filament\Notifications\Notification::make()
                                ->title('No se pudo generar PDF')
                                ->body($result['message'] ?? 'Revisa el recibo CFDI nómina.')
                                ->danger()
                                ->send();

                            return;
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('PDF generado')
                            ->body((string) ($result['summary']['pdf_path'] ?? 'PDF listo.'))
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('view_payroll_cfdi_pdf')
                    ->label('Ver PDF')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->visible(fn ($record): bool => filled($record->pdf_path))
                    ->url(fn ($record): string => route('payroll-cfdi-receipts.pdf', ['receipt' => $record->id]))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('stamp_payroll_cfdi')
                    ->label('Timbrar CFDI')
                    ->icon('heroicon-o-shield-check')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Timbrar CFDI nómina')
                    ->modalDescription('Esto intentará timbrar el CFDI nómina ante el PAC/SAT. En DEV debe quedar bloqueado por seguridad. El timbrado real solo está permitido en PROD.')
                    ->modalSubmitActionLabel('Sí, intentar timbrar')
                    ->visible(fn ($record): bool => in_array((string) $record->status, ['validated', 'error'], true) && blank($record->uuid) && filled($record->xml_path))
                    ->action(function ($record): void {
                        $result = app(\App\Support\PayrollCfdi\PayrollCfdiStampService::class)->stamp(
                            companyId: (int) $record->company_id,
                            receiptId: (int) $record->id,
                            userId: auth()->id(),
                        );

                        if (! ($result['success'] ?? false)) {
                            \Filament\Notifications\Notification::make()
                                ->title(($result['blocked'] ?? false) ? 'Timbrado bloqueado' : 'No se pudo timbrar')
                                ->body(collect($result['errors'] ?? [])->take(6)->implode(PHP_EOL) ?: ($result['message'] ?? 'Revisa el detalle del recibo CFDI nómina.'))
                                ->danger()
                                ->send();

                            return;
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('CFDI nómina timbrado')
                            ->body('UUID: ' . (($result['summary']['uuid'] ?? null) ?: 'N/D'))
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('download_xml_draft')
                    ->label('Descargar XML')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->visible(fn ($record): bool => filled($record->xml_path))
                    ->action(function ($record) {
                        if (! filled($record->xml_path) || ! \Illuminate\Support\Facades\Storage::disk('local')->exists($record->xml_path)) {
                            \Filament\Notifications\Notification::make()
                                ->title('XML no disponible')
                                ->body('El archivo XML no existe o no esta disponible en storage.')
                                ->danger()
                                ->send();

                            return null;
                        }

                        $folio = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) ($record->folio ?: ('recibo_' . $record->id)));

                        return response()->download(
                            \Illuminate\Support\Facades\Storage::disk('local')->path($record->xml_path),
                            'cfdi_nomina_' . $folio . '.xml',
                            ['Content-Type' => 'application/xml']
                        );
                    }),

                Tables\Actions\Action::make('prepare_receipt_xml_draft')
                    ->label('Generar XML')
                    ->icon('heroicon-o-document-text')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Generar XML CFDI nomina')
                    ->modalDescription('Generara XML CFDI de nomina en borrador para la corrida de este recibo. No timbra ni envia datos al PAC/SAT.')
                    ->visible(fn ($record): bool => ! in_array((string) $record->status, ['stamped', 'cancelled'], true))
                    ->action(function ($record): void {
                        $result = app(\App\Support\PayrollCfdi\PayrollCfdiXmlDraftService::class)->prepareForRun(
                            companyId: (int) $record->company_id,
                            payrollRunId: (int) $record->payroll_run_id,
                            userId: auth()->id(),
                            force: false,
                        );

                        if (! ($result['success'] ?? false)) {
                            \Filament\Notifications\Notification::make()
                                ->title('No se pudo generar XML CFDI')
                                ->body(collect($result['errors'] ?? [])->take(5)->implode(PHP_EOL) ?: ($result['message'] ?? 'Revisa los datos del recibo.'))
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

                Tables\Actions\ViewAction::make()->label('Ver'),
            ])
            ->bulkActions([])
            ->defaultSort('id', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Recibo CFDI nómina')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('status')
                            ->label('Estado')
                            ->formatStateUsing(fn (?string $state): string => self::statusOptions()[$state] ?? ($state ?: 'Sin estado'))
                            ->badge(),

                        Infolists\Components\TextEntry::make('series')
                            ->label('Serie'),

                        Infolists\Components\TextEntry::make('folio')
                            ->label('Folio')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('payrollRun.name')
                            ->label('Nómina'),

                        Infolists\Components\TextEntry::make('employee.name')
                            ->label('Empleado'),

                        Infolists\Components\TextEntry::make('uuid')
                            ->label('UUID')
                            ->placeholder('Sin timbrar')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('cfdi_version')
                            ->label('CFDI'),

                        Infolists\Components\TextEntry::make('payroll_complement_version')
                            ->label('Complemento nómina'),

                        Infolists\Components\TextEntry::make('validated_at')
                            ->label('Validado')
                            ->dateTime('Y-m-d H:i'),

                        Infolists\Components\TextEntry::make('stamped_at')
                            ->label('Timbrado')
                            ->dateTime('Y-m-d H:i')
                            ->placeholder('Pendiente'),

                        Infolists\Components\TextEntry::make('cancelled_at')
                            ->label('Cancelado')
                            ->dateTime('Y-m-d H:i')
                            ->placeholder('No cancelado'),

                        Infolists\Components\TextEntry::make('pac_provider')
                            ->label('PAC')
                            ->placeholder('Sin PAC'),
                    ]),

                Infolists\Components\Section::make('Totales')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('totals_snapshot')
                            ->label('Período')
                            ->formatStateUsing(fn ($state): string => self::periodSummary($state)),

                        Infolists\Components\TextEntry::make('totals_snapshot_gross')
                            ->label('Total bruto')
                            ->state(fn (PayrollCfdiReceipt $record): string => self::moneyFromSnapshot($record->totals_snapshot, 'gross_amount')),

                        Infolists\Components\TextEntry::make('totals_snapshot_deductions')
                            ->label('Deducciones')
                            ->state(fn (PayrollCfdiReceipt $record): string => self::moneyFromSnapshot($record->totals_snapshot, 'deductions_amount')),

                        Infolists\Components\TextEntry::make('totals_snapshot_net')
                            ->label('Neto')
                            ->state(fn (PayrollCfdiReceipt $record): string => self::moneyFromSnapshot($record->totals_snapshot, 'net_amount')),
                    ]),

                Infolists\Components\Section::make('XML borrador')
                    ->description('XML generado localmente. No timbrado, sin UUID y sin envio a PAC/SAT.')
                    ->schema([
                        Infolists\Components\TextEntry::make('xml_path')
                            ->label('Ruta XML')
                            ->placeholder('Sin XML generado')
                            ->copyable()
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('xml_content')
                            ->label('Contenido XML')
                            ->state(function (PayrollCfdiReceipt $record): string {
                                if (! filled($record->xml_path)) {
                                    return 'Sin XML generado.';
                                }

                                if (! \Illuminate\Support\Facades\Storage::disk('local')->exists($record->xml_path)) {
                                    return 'El XML no existe en storage.';
                                }

                                return \Illuminate\Support\Facades\Storage::disk('local')->get($record->xml_path);
                            })
                            ->copyable()
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Snapshots fiscales')
                    ->schema([
                        Infolists\Components\TextEntry::make('issuer_snapshot')
                            ->label('Emisor')
                            ->formatStateUsing(fn ($state): string => self::snapshotText($state))
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('employee_snapshot')
                            ->label('Empleado/Receptor')
                            ->formatStateUsing(fn ($state): string => self::snapshotText($state))
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('contract_snapshot')
                            ->label('Contrato')
                            ->formatStateUsing(fn ($state): string => self::snapshotText($state))
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('validation_errors')
                            ->label('Errores de validación')
                            ->formatStateUsing(fn ($state): string => self::snapshotText($state))
                            ->placeholder('Sin errores')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = Filament::getTenant()?->getKey();

        return parent::getEloquentQuery()
            ->with(['employee', 'payrollRun'])
            ->when($tenantId, fn (Builder $query) => $query->where('company_id', $tenantId));
    }

    public static function getNavigationBadge(): ?string
    {
        $tenantId = Filament::getTenant()?->getKey();

        $count = static::getModel()::query()
            ->when($tenantId, fn (Builder $query) => $query->where('company_id', $tenantId))
            ->where('status', 'draft')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function statusOptions(): array
    {
        return [
            'draft' => 'Borrador',
            'validated' => 'Validado',
            'stamping' => 'Timbrando',
            'stamped' => 'Timbrado',
            'cancelled' => 'Cancelado',
            'error' => 'Error',
        ];
    }

    protected static function snapshotText(mixed $state): string
    {
        if ($state === null || $state === '') {
            return '-';
        }

        if (is_string($state)) {
            $decoded = json_decode($state, true);
            $state = json_last_error() === JSON_ERROR_NONE ? $decoded : $state;
        }

        if (is_array($state)) {
            return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '-';
        }

        return (string) $state;
    }

    protected static function periodSummary(mixed $state): string
    {
        if (is_string($state)) {
            $decoded = json_decode($state, true);
            $state = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        if (! is_array($state)) {
            return '-';
        }

        $start = $state['period_start'] ?? '-';
        $end = $state['period_end'] ?? '-';
        $payment = $state['payment_date'] ?? '-';

        return "Periodo: {$start} a {$end}. Pago: {$payment}.";
    }

    protected static function moneyFromSnapshot(mixed $state, string $key): string
    {
        if (is_string($state)) {
            $decoded = json_decode($state, true);
            $state = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        if (! is_array($state)) {
            return '$0.00';
        }

        return '$' . number_format((float) ($state[$key] ?? 0), 2);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrollCfdiReceipts::route('/'),
            'view' => Pages\ViewPayrollCfdiReceipt::route('/{record}'),
        ];
    }
}
