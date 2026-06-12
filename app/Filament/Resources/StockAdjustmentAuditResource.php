<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockAdjustmentAuditResource\Pages;
use App\Models\StockAdjustmentAudit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class StockAdjustmentAuditResource extends Resource
{
    protected static ?string $model = StockAdjustmentAudit::class;

        // BEXIA_V5729Y_TENANT_COMPANY_RELATIONSHIP
    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Inventario';

    protected static ?string $navigationLabel = 'Auditoría de ajustes';

    protected static ?string $modelLabel = 'auditoría de ajuste';

    protected static ?string $pluralModelLabel = 'auditoría de ajustes';

    protected static ?int $navigationSort = 74;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('stock_adjustment_audits', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Detalle')
                ->schema([
                    Forms\Components\TextInput::make('event')
                        ->label('Evento')
                        ->disabled(),

                    Forms\Components\Textarea::make('description')
                        ->label('Descripción')
                        ->disabled()
                        ->columnSpanFull(),

                    Forms\Components\KeyValue::make('before_data')
                        ->label('Antes')
                        ->disabled()
                        ->columnSpanFull(),

                    Forms\Components\KeyValue::make('after_data')
                        ->label('Después')
                        ->disabled()
                        ->columnSpanFull(),

                    Forms\Components\KeyValue::make('metadata')
                        ->label('Metadatos')
                        ->disabled()
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('event')
                    ->label('Evento')
                    ->formatStateUsing(fn (?string $state): string => static::eventLabel($state))
                    ->badge()
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('stock_adjustment_id')
                    ->label('Ajuste')
                    ->formatStateUsing(fn ($state): string => $state ? 'Ajuste #' . $state : '—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('stock_adjustment_line_id')
                    ->label('Línea')
                    ->formatStateUsing(fn ($state): string => $state ? 'Línea #' . $state : '—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('user_id')
                    ->label('Usuario')
                    ->formatStateUsing(fn ($state): string => $state ? 'Usuario #' . $state : 'Sistema')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event')
                    ->label('Evento')
                    ->options([
                        'created' => 'Creado',
                        'updated' => 'Actualizado',
                        'confirmed' => 'Confirmado',
                        'cancelled' => 'Cancelado',
                        'line_created' => 'Línea creada',
                        'line_updated' => 'Línea actualizada',
                        'line_deleted' => 'Línea eliminada',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Ver'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockAdjustmentAudits::route('/'),
            'view' => Pages\ViewStockAdjustmentAudit::route('/{record}'),
        ];
    }

    protected static function currentCompanyId(): ?int
    {
        foreach (['company_id', 'current_company_id', 'active_company_id', 'tenant_company_id'] as $key) {
            if (session($key)) {
                return (int) session($key);
            }
        }

        return null;
    }

    protected static function eventLabel(?string $event): string
    {
        return match ($event) {
            'created' => 'Creado',
            'updated' => 'Actualizado',
            'confirmed' => 'Confirmado',
            'cancelled' => 'Cancelado',
            'line_created' => 'Línea creada',
            'line_updated' => 'Línea actualizada',
            'line_deleted' => 'Línea eliminada',
            default => $event ?: 'Evento',
        };
    }
}
