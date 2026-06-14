<?php

namespace App\Support\AiInsights;

class AiInsightsToolRegistry
{
    public static function availableTools(): array
    {
        return [
            [
                'key' => 'sales_summary',
                'label' => 'Resumen de ventas',
                'enabled' => false,
                'status' => 'Pendiente de implementar',
            ],
            [
                'key' => 'accounts_receivable_aging',
                'label' => 'Cuentas por cobrar vencidas',
                'enabled' => false,
                'status' => 'Pendiente de implementar',
            ],
            [
                'key' => 'accounts_payable_summary',
                'label' => 'Cuentas por pagar',
                'enabled' => false,
                'status' => 'Pendiente de implementar',
            ],
            [
                'key' => 'inventory_low_stock',
                'label' => 'Inventario bajo',
                'enabled' => false,
                'status' => 'Pendiente de implementar',
            ],
            [
                'key' => 'treasury_cash_position',
                'label' => 'Posición de tesorería',
                'enabled' => false,
                'status' => 'Pendiente de implementar',
            ],
        ];
    }
}
