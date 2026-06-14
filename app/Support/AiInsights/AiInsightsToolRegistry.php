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
                'enabled' => true,
                'status' => 'Activa: Ventas + PDV + devoluciones PDV',
            ],
            [
                'key' => 'accounts_receivable_aging',
                'label' => 'Cuentas por cobrar vencidas',
                'enabled' => false,
                'status' => 'Pendiente de conectar como herramienta segura',
            ],
            [
                'key' => 'accounts_payable_summary',
                'label' => 'Cuentas por pagar',
                'enabled' => false,
                'status' => 'Pendiente de conectar como herramienta segura',
            ],
            [
                'key' => 'inventory_low_stock',
                'label' => 'Inventario bajo',
                'enabled' => false,
                'status' => 'Pendiente de conectar como herramienta segura',
            ],
            [
                'key' => 'treasury_cash_position',
                'label' => 'Posición de tesorería',
                'enabled' => false,
                'status' => 'Pendiente de conectar como herramienta segura',
            ],
            [
                'key' => 'payroll_safe_summary',
                'label' => 'Nómina resumen seguro',
                'enabled' => false,
                'status' => 'Pendiente; requiere reglas especiales de confidencialidad',
            ],
        ];
    }
}
