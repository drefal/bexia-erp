<?php

namespace App\Filament\Resources\InvoiceResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class CfdiAuditsRelationManager extends RelationManager
{
    protected static string $relationship = 'cfdiAudits';

    protected static ?string $title = 'Auditoría CFDI';

    protected static ?string $modelLabel = 'auditoría CFDI';

    protected static ?string $pluralModelLabel = 'auditoría CFDI';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /*
         * BEXIA_V5523V4_CFDI_AUDIT_TAB_ADMIN_ONLY
         * La auditoría CFDI muestra movimientos fiscales sensibles.
         * Solo administradores o usuarios con permiso explícito pueden verla.
         */
        return self::canViewCfdiAuditTab();
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                TextColumn::make('action')
                    ->label('Acción')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::cfdiAuditActionLabel($state))
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (?string $state): string => self::cfdiAuditStatusColor($state))
                    ->formatStateUsing(fn (?string $state): string => self::cfdiAuditStatusLabel($state)),

                TextColumn::make('pac')
                    ->label('PAC')
                    ->formatStateUsing(fn ($state): string => filled($state) ? strtoupper((string) $state) : '-'),

                TextColumn::make('environment')
                    ->label('Ambiente')
                    ->formatStateUsing(fn ($state): string => self::cfdiAuditEnvironmentLabel($state)),

                TextColumn::make('message')
                    ->label('Mensaje')
                    ->wrap()
                    ->limit(160),
            ])
            ->actions([
                Tables\Actions\Action::make('detail')
                    ->label('Detalle')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading('Detalle de auditoría CFDI')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalContent(fn ($record): HtmlString => self::cfdiAuditDetailHtml($record)),
            ])
            ->paginated([10, 25, 50]);
    }

    private static function canViewCfdiAuditTab(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        $isAdmin = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole([
                'Super Administrador',
                'Administrador',
                'Admin',
                'admin',
                'super_admin',
            ])
            : false;

        return $isAdmin
            || $user->can('settings.access')
            || $user->can('invoicing.audit');
    }

    private static function cfdiAuditActionLabel(?string $state): string
    {
        return match ((string) $state) {
            'validate' => 'Validación CFDI',
            'assign_folio' => 'Asignación de folio',
            'generate_signed_xml' => 'Generación de XML firmado',
            'stamp' => 'Timbrado CFDI',
            'generate_pdf' => 'Generación de PDF',
            'send_cfdi_email' => 'Envío por correo',
            'send_cfdi_email_resend' => 'Reenvío por correo',
            'download_cfdi_pdf' => 'Descarga PDF',
            'download_cfdi_xml' => 'Descarga XML',
            'download_cfdi_zip' => 'Descarga ZIP',
            'prepare_cfdi_cancel' => 'Preparación de cancelación',
            'send_cfdi_cancel' => 'Envío de cancelación PAC/SAT',
            'query_cfdi_cancel_status' => 'Consulta de cancelación',
            default => filled($state) ? (string) $state : 'Sin acción',
        };
    }

    private static function cfdiAuditStatusLabel(?string $state): string
    {
        return match ((string) $state) {
            'success' => 'Correcto',
            'error' => 'Error',
            'ready_to_cancel' => 'Listo para cancelar',
            'sending_to_pac' => 'Enviando al PAC/SAT',
            'cancel_requested' => 'Cancelación solicitada',
            'cancel_error' => 'Error de cancelación',
            'cancelled', 'canceled' => 'Cancelado',
            'pending' => 'Pendiente',
            'accepted' => 'Aceptado',
            'rejected' => 'Rechazado',
            default => filled($state) ? (string) $state : 'Sin estado',
        };
    }

    private static function cfdiAuditStatusColor(?string $state): string
    {
        return match ((string) $state) {
            'success', 'accepted' => 'success',
            'cancelled', 'canceled' => 'danger',
            'cancel_requested', 'ready_to_cancel', 'sending_to_pac', 'pending' => 'warning',
            'error', 'rejected' => 'danger',
            default => 'gray',
        };
    }

    private static function cfdiAuditEnvironmentLabel($state): string
    {
        return match ((string) $state) {
            'production' => 'Producción',
            'test', 'testing', 'sandbox' => 'Pruebas',
            default => filled($state) ? (string) $state : '-',
        };
    }

    private static function cfdiAuditDetailHtml($record): HtmlString
    {
        $items = [
            'Fecha' => optional($record->created_at)->format('d/m/Y H:i:s') ?: (string) ($record->created_at ?? ''),
            'Acción' => self::cfdiAuditActionLabel($record->action ?? null),
            'Estado' => self::cfdiAuditStatusLabel($record->status ?? null),
            'PAC' => filled($record->pac ?? null) ? strtoupper((string) $record->pac) : '-',
            'Ambiente' => self::cfdiAuditEnvironmentLabel($record->environment ?? null),
            'Mensaje' => (string) ($record->message ?? ''),
        ];

        $html = '<div class="space-y-3 text-sm">';

        foreach ($items as $label => $value) {
            $html .= '<div>';
            $html .= '<div class="font-semibold">'.e($label).'</div>';
            $html .= '<div class="text-gray-700 dark:text-gray-300">'.nl2br(e($value)).'</div>';
            $html .= '</div>';
        }

        /*
         * BEXIA_V5526V_SHOW_AUDIT_META_DETAIL
         */
        foreach ([
            'Datos enviados' => $record->request_meta ?? null,
            'Respuesta recibida' => $record->response_meta ?? null,
        ] as $label => $jsonValue) {
            $decoded = is_string($jsonValue) && trim($jsonValue) !== ''
                ? json_decode($jsonValue, true)
                : null;

            if (is_array($decoded) && $decoded !== []) {
                $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $html .= '<div>';
                $html .= '<div class="font-semibold">'.e($label).'</div>';
                $html .= '<pre class="text-xs whitespace-pre-wrap rounded-lg bg-gray-100 dark:bg-gray-900 p-3 overflow-auto">'.e((string) $pretty).'</pre>';
                $html .= '</div>';
            }
        }

        $html .= '</div>';

        return new HtmlString($html);
    }
}
