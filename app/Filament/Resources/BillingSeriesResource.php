<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BillingSeriesResource\Pages;
use App\Models\BillingSeries;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BillingSeriesResource extends Resource
{
    protected static ?string $model = BillingSeries::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-numbered-list';

    protected static ?string $navigationGroup = 'Facturación';

    protected static ?string $navigationLabel = 'Series de facturación';

    protected static ?string $modelLabel = 'serie de facturación';

    protected static ?string $pluralModelLabel = 'series de facturación';

    protected static ?string $slug = 'billing-series';

    protected static ?int $navigationSort = 30;

public static function canCreate(): bool
    {
        return static::canManage();
    }

    public static function canEdit($record): bool
    {
        return static::canManage();
    }

    public static function canDelete($record): bool
    {
        return static::canManage();
    }

    public static function canManage(): bool
    {
        $user = auth()->user();

        return (bool) ($user && method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin());
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('company');

        if (! static::canManage()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->orderBy('company_id')->orderBy('document_type')->orderBy('id');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
            'resources.billingseriesresource',
            fn (): bool => static::bexiaBaseShouldRegisterNavigation(),
        );
    }

    protected static function bexiaBaseShouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('invoicing.view')
            );
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('invoicing.view')
            );
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Contexto')
                ->columns(12)
                ->schema([
                    Forms\Components\Select::make('company_id')
                        ->label('Empresa')
                        ->options(fn (): array => static::companyOptions())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpan(4),

                    Forms\Components\Select::make('document_type')
                        ->label('Tipo de documento')
                        ->options([
                            'invoice' => 'Factura',
                            'credit_note' => 'Nota de crédito',
                            'payment_complement' => 'Complemento de pago',
                            'global_invoice' => 'Factura global POS',
                        ])
                        ->default('invoice')
                        ->required()
                        ->columnSpan(3),

                    Forms\Components\Select::make('branch_id')
                        ->label('Sucursal')
                        ->options(fn (): array => static::branchOptions())
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->helperText('Opcional. Si no seleccionas sucursal, aplica a toda la empresa.')
                        ->columnSpan(3),

                    Forms\Components\Select::make('pos_point_id')
                        ->label('Punto de venta')
                        ->options(fn (): array => static::posPointOptions())
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->helperText('Opcional. Tiene prioridad sobre la sucursal.')
                        ->columnSpan(2),
                ]),

            Forms\Components\Section::make('Serie y folio')
                ->columns(12)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nombre interno')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Solo para identificar esta configuración dentro de Bexia. No se envía al SAT ni aparece en el XML.')
                        ->columnSpan(4),

                    Forms\Components\TextInput::make('series')
                        ->label('Serie CFDI')
                        ->required()
                        ->maxLength(80)
                        ->helperText('Ejemplo: INV 2026, PP 2026, CEN 2026.')
                        ->columnSpan(3),

                    Forms\Components\TextInput::make('year')
                        ->label('Año')
                        ->numeric()
                        ->default((int) date('Y'))
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('next_number')
                        ->label('Siguiente folio')
                        ->numeric()
                        ->minValue(1)
                        ->default(1)
                        ->required()
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('padding')
                        ->label('Dígitos vista')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(12)
                        ->default(5)
                        ->helperText('Solo para visualización: 3110 => 03110.')
                        ->columnSpan(1),

                    Forms\Components\Select::make('reset_period')
                        ->label('Reinicio')
                        ->options([
                            'never' => 'Nunca',
                            'yearly' => 'Anual',
                        ])
                        ->default('yearly')
                        ->required()
                        ->columnSpan(3),

                    Forms\Components\Toggle::make('is_default')
                        ->label('Serie default')
                        ->helperText('Se usa si no hay serie específica para sucursal/PDV.')
                        ->default(false)
                        ->columnSpan(2),

                    Forms\Components\Toggle::make('active')
                        ->label('Activa')
                        ->default(true)
                        ->columnSpan(2),

                    Forms\Components\Placeholder::make('preview')
                        ->label('Vista previa')
                        ->content(function (Forms\Get $get): string {
                            $series = trim((string) $get('series'));
                            $number = (int) ($get('next_number') ?: 1);
                            $padding = (int) ($get('padding') ?: 5);
                            $folio = str_pad((string) $number, max(1, $padding), '0', STR_PAD_LEFT);

                            return ($series !== '' ? $series : 'INV ' . date('Y')) . '/' . $folio;
                        })
                        ->columnSpan(5),

                    Forms\Components\Textarea::make('notes')
                        ->label('Notas')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company.name')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('document_type')
                    ->label('Documento')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match ((string) $state) {
                        'invoice' => 'Factura',
                        'credit_note' => 'Nota de crédito',
                        'payment_complement' => 'Complemento de pago',
                        'global_invoice' => 'Factura global POS',
                        default => (string) $state,
                    }),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre interno')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('series')
                    ->label('Serie')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('next_number')
                    ->label('Siguiente')
                    ->sortable(),

                Tables\Columns\TextColumn::make('preview')
                    ->label('Vista previa')
                    ->state(fn (BillingSeries $record): string => $record->previewNextNumber()),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),

                Tables\Columns\IconColumn::make('active')
                    ->label('Activa')
                    ->boolean(),

                Tables\Columns\TextColumn::make('last_assigned_at')
                    ->label('Último uso')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('company_id')
                    ->label('Empresa')
                    ->options(fn (): array => static::companyOptions()),

                Tables\Filters\SelectFilter::make('document_type')
                    ->label('Documento')
                    ->options([
                        'invoice' => 'Factura',
                        'credit_note' => 'Nota de crédito',
                        'payment_complement' => 'Complemento de pago',
                        'global_invoice' => 'Factura global POS',
                    ]),

                Tables\Filters\TernaryFilter::make('active')
                    ->label('Activa'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nueva serie'),
            ]);
    }

    public static function companyOptions(): array
    {
        if (! Schema::hasTable('companies')) {
            return [];
        }

        return DB::table('companies')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn ($row): array => [(int) $row->id => 'ID ' . $row->id . ' - ' . (string) $row->name])
            ->all();
    }

    public static function branchOptions(): array
    {
        foreach (['branches', 'company_branches', 'stores', 'locations'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $columns = Schema::getColumnListing($table);
            $labelColumn = in_array('name', $columns, true) ? 'name' : (in_array('title', $columns, true) ? 'title' : 'id');

            return DB::table($table)
                ->orderBy($labelColumn)
                ->get()
                ->mapWithKeys(fn ($row): array => [(int) $row->id => 'ID ' . $row->id . ' - ' . (string) ($row->{$labelColumn} ?? $row->id)])
                ->all();
        }

        return [];
    }

    public static function posPointOptions(): array
    {
        foreach (['pos_points', 'point_of_sales', 'pos_registers'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $columns = Schema::getColumnListing($table);
            $labelColumn = in_array('name', $columns, true) ? 'name' : (in_array('title', $columns, true) ? 'title' : 'id');

            return DB::table($table)
                ->orderBy($labelColumn)
                ->get()
                ->mapWithKeys(fn ($row): array => [(int) $row->id => 'ID ' . $row->id . ' - ' . (string) ($row->{$labelColumn} ?? $row->id)])
                ->all();
        }

        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBillingSeries::route('/'),
            'create' => Pages\CreateBillingSeries::route('/create'),
            'edit' => Pages\EditBillingSeries::route('/{record}/edit'),
        ];
    }
}
