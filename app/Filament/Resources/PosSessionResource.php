<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PosSessionResource\Pages;
use App\Models\PosSession;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PosSessionResource extends Resource
{
    protected static ?string $model = PosSession::class;

    protected static ?string $navigationGroup = 'Punto de Venta';

    protected static ?string $navigationLabel = 'Sesiones PDV';

    protected static ?string $modelLabel = 'Sesión PDV';

    protected static ?string $pluralModelLabel = 'Sesiones PDV';

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?int $navigationSort = 80;

    protected static bool $isScopedToTenant = false;

public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        // V5.50.1D - evitar tenant scope automático de Filament.
        // PosSession no tiene relación companies; se filtra manualmente por company_id.
        $query = PosSession::query();

        $tenant = filament()->getTenant();

        if (
            $tenant
            && method_exists($tenant, 'getKey')
            && Schema::hasColumn('pos_sessions', 'company_id')
        ) {
            $query->where('company_id', $tenant->getKey());
        }

        return $query;
    }

    protected static function v5501aPriceListChangesForSession($record)
    {
        if (! Schema::hasTable('pos_price_list_changes')) {
            return collect();
        }

        $query = DB::table('pos_price_list_changes as plc')
            ->where('plc.pos_session_id', $record->id)
            ->orderByDesc('plc.changed_at')
            ->orderByDesc('plc.id');

        if (Schema::hasTable('users')) {
            $query->leftJoin('users as u', 'u.id', '=', 'plc.user_id');
        }

        if (Schema::hasTable('contacts')) {
            $query->leftJoin('contacts as c', 'c.id', '=', 'plc.customer_id');
        }

        return $query
            ->limit(200)
            ->get([
                'plc.id',
                'plc.pos_session_id',
                'plc.user_id',
                'plc.customer_id',
                'plc.previous_price_list_id',
                'plc.previous_price_list_name',
                'plc.new_price_list_id',
                'plc.new_price_list_name',
                'plc.source',
                'plc.changed_at',
                DB::raw(Schema::hasTable('users') ? 'u.name as user_name' : 'NULL as user_name'),
                DB::raw(Schema::hasTable('contacts') ? 'c.name as customer_name' : 'NULL as customer_name'),
            ]);
    }

public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('pos.menu.view')
            );
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('pos.menu.view')
            );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Sesión')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match ((string) $state) {
                        'open' => 'Abierto',
                        'closed' => 'Cerrado',
                        'cancelled' => 'Cancelado',
                        default => ucfirst((string) $state),
                    })
                    ->color(fn ($state): string => match ((string) $state) {
                        'open' => 'success',
                        'closed' => 'gray',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('opened_at')
                    ->label('Apertura')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('closed_at')
                    ->label('Cierre')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('Aún abierta')
                    ->sortable(),

                Tables\Columns\TextColumn::make('opening_amount')
                    ->label('Fondo inicial')
                    ->money('MXN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('closing_amount')
                    ->label('Fondo cierre')
                    ->money('MXN')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'open' => 'Abierto',
                        'closed' => 'Cerrado',
                        'cancelled' => 'Cancelado',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Ver sesión')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->visible(fn (): bool => auth()->user()?->can('pos.sessions.view_detail') ?? false),

                Tables\Actions\Action::make('open_pos')
                    ->label('Abrir PDV')
                    ->icon('heroicon-o-computer-desktop')
                    ->color('primary')
                    ->visible(fn ($record): bool => (string) $record->status === 'open' && (auth()->user()?->can('pos.sessions.open_pos') ?? false))
                    ->url(fn ($record): string => url('/pos/sessions/' . $record->id . '/screen'))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosSessions::route('/'),
            'view' => Pages\ViewPosSession::route('/{record}'),
        ];
    }
}
