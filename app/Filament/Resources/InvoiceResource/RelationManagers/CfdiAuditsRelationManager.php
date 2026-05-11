<?php

namespace App\Filament\Resources\InvoiceResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class CfdiAuditsRelationManager extends RelationManager
{
    protected static string $relationship = 'cfdiAudits';

    protected static ?string $title = 'Auditoría CFDI';

    protected static ?string $modelLabel = 'evento CFDI';

    protected static ?string $pluralModelLabel = 'auditoría CFDI';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('action')
                    ->label('Acción')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match ((string) $state) {
                        'validate' => 'Validación',
                        'stamp' => 'Timbrado',
                        'cancel' => 'Cancelación',
                        default => (string) $state,
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn ($state): string => match ((string) $state) {
                        'success' => 'success',
                        'error' => 'danger',
                        'warning' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('pac_provider')
                    ->label('PAC')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('pac_environment')
                    ->label('Ambiente')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('message')
                    ->label('Mensaje')
                    ->limit(80)
                    ->wrap(),
            ])
            ->actions([
                Tables\Actions\Action::make('details')
                    ->label('Detalle')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Detalle de auditoría CFDI')
                    ->modalWidth('5xl')
                    ->modalContent(function ($record): HtmlString {
                        $request = json_encode($record->request_meta ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        $response = json_encode($record->response_meta ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                        $html = '<div style="display:grid; gap:16px;">';
                        $html .= '<div><strong>Mensaje</strong><br>' . e((string) ($record->message ?? '')) . '</div>';
                        $html .= '<div><strong>Request seguro</strong><pre style="white-space:pre-wrap; font-size:12px;">' . e($request ?: '{}') . '</pre></div>';
                        $html .= '<div><strong>Response seguro</strong><pre style="white-space:pre-wrap; font-size:12px;">' . e($response ?: '{}') . '</pre></div>';
                        $html .= '</div>';

                        return new HtmlString($html);
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated(10);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
