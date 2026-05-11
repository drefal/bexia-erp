<?php

namespace App\Support;

class PermissionLabels
{
    public static function label(string $permission): string
    {
        $labels = static::labels();

        if (isset($labels[$permission])) {
            return $labels[$permission];
        }

        return static::fallbackLabel($permission);
    }

    public static function display(string $permission): string
    {
        return static::label($permission) . ' (' . $permission . ')';
    }

    public static function labels(): array
    {
        return [
            'company.view' => 'Ver empresas',
            'company.update' => 'Editar empresas',

            'contacts.view' => 'Ver contactos',
            'contacts.create' => 'Crear contactos',
            'contacts.update' => 'Editar contactos',
            'contacts.delete' => 'Eliminar contactos',
            'contacts.restore' => 'Restaurar contactos',
            'contacts.import_csf' => 'Importar CSF de contactos',
            'contacts.view_csf' => 'Ver CSF de contactos',

            'inventory.view' => 'Ver inventario',
            'inventory.create' => 'Crear inventario',
            'inventory.update' => 'Editar inventario',
            'inventory.delete' => 'Eliminar inventario',
            'inventory.adjust_stock' => 'Ajustar inventario',
            'inventory.transfer_stock' => 'Trasladar inventario',
            'inventory.download_inventory_template' => 'Descargar plantilla de inventario',
            'inventory.import_inventory_template' => 'Importar conteo CSV',
            'inventory.zero_untracked_stock' => 'Poner a cero existencias sin seguimiento',
            'inventory.view_replenishment_rules' => 'Ver reglas de reabastecimiento',
            'inventory.manage_replenishment_rules' => 'Administrar reglas de reabastecimiento',
            'inventory.view_replenishment_report' => 'Ver reporte de reabastecimiento',
            'inventory.view_suggested_purchase_list' => 'Ver lista sugerida de compra',
            'purchases.manage_purchase_requests' => 'Administrar solicitudes de compra',
            'approvals.approve' => 'Aprobar documentos',
            'approvals.manage_workflows' => 'Administrar flujos de aprobación',
            'approvals.view_workflows' => 'Ver flujos de aprobación',
            'purchases.view_purchase_requests' => 'Ver solicitudes de compra',
            'inventory.view_product_price_cost_audit' => 'Ver auditoría de precios y costos de productos',

            'invoicing.view' => 'Ver facturación',
            'invoicing.create' => 'Crear facturas',
            'invoicing.stamp' => 'Timbrar facturas',
            'invoicing.cancel' => 'Cancelar facturas',
            'invoicing.download_pdf' => 'Descargar PDF de factura',
            'invoicing.download_xml' => 'Descargar XML de factura',

            'payment_terms.view' => 'Ver términos de pago',
            'payment_terms.create' => 'Crear términos de pago',
            'payment_terms.update' => 'Editar términos de pago',
            'payment_terms.delete' => 'Eliminar términos de pago',

            'accounting.view' => 'Ver contabilidad',
            'accounting.update' => 'Editar contabilidad',
            'accounting.delete' => 'Eliminar contabilidad',

            'purchases.view' => 'Ver compras',
            'purchases.create' => 'Crear compras',
            'purchases.update' => 'Editar compras',
            'purchases.delete' => 'Eliminar compras',
            'purchases.approve' => 'Aprobar compras',
            'purchases.receive' => 'Recibir compras',
            'purchases.invoice' => 'Facturar compras',

            'sales.view' => 'Ver ventas',
            'sales.create' => 'Crear ventas',
            'sales.update' => 'Editar ventas',
            'sales.delete' => 'Eliminar ventas',
            'sales.approve' => 'Aprobar ventas',
            'sales.deliver' => 'Entregar ventas',
            'sales.invoice' => 'Facturar ventas',

            'pos.cash_count' => 'Conteo de caja POS',
            'pos.open_shift' => 'Abrir turno POS',
            'pos.close_shift' => 'Cerrar turno POS',
            'pos.sale_shift' => 'Venta en turno POS',
            'pos.discount' => 'Aplicar descuentos POS',
            'pos.refund' => 'Realizar devoluciones POS',

            'reports.view' => 'Ver reportes',
            'reports.accounting' => 'Ver reportes contables',
            'reports.inventory' => 'Ver reportes de inventario',
            'reports.purchases' => 'Ver reportes de compras',
            'reports.sales' => 'Ver reportes de ventas',

            'roles.view' => 'Ver roles',
            'roles.manage' => 'Administrar roles',
            'rol.view' => 'Ver roles',
            'rol.manage' => 'Administrar roles',

            'salidas.ver' => 'Ver salidas',
            'salidas.ver_todas' => 'Ver todas las salidas',
            'salidas.create' => 'Crear salidas',
            'salidas.update' => 'Editar salidas',
            'salidas.delete' => 'Eliminar salidas',
            'salidas.enviar_pdf' => 'Enviar PDF de salida',
            'salidas.configurar' => 'Configurar salidas',

            'settings.access' => 'Acceso a configuración',

            'user_access.view' => 'Ver accesos de usuario',
            'user_access.update' => 'Editar accesos de usuario',

            'users.view' => 'Ver usuarios',
            'users.create' => 'Crear usuarios',
            'users.update' => 'Editar usuarios',
            'users.delete' => 'Eliminar usuarios',
        ];
    }

    protected static function fallbackLabel(string $permission): string
    {
        $moduleLabels = [
            'accounting' => 'contabilidad',
            'company' => 'empresas',
            'contacts' => 'contactos',
            'inventory' => 'inventario',
            'invoicing' => 'facturación',
            'payment_terms' => 'términos de pago',
            'pos' => 'POS',
            'purchases' => 'compras',
            'reports' => 'reportes',
            'roles' => 'roles',
            'rol' => 'roles',
            'sales' => 'ventas',
            'salidas' => 'salidas',
            'settings' => 'configuración',
            'user_access' => 'accesos de usuario',
            'users' => 'usuarios',
        ];

        $actionLabels = [
            'view' => 'Ver',
            'create' => 'Crear',
            'update' => 'Editar',
            'delete' => 'Eliminar',
            'restore' => 'Restaurar',
            'manage' => 'Administrar',
            'access' => 'Acceder a',
            'approve' => 'Aprobar',
            'receive' => 'Recibir',
            'invoice' => 'Facturar',
            'deliver' => 'Entregar',
            'stamp' => 'Timbrar',
            'cancel' => 'Cancelar',
            'download_pdf' => 'Descargar PDF de',
            'download_xml' => 'Descargar XML de',
            'import_csf' => 'Importar CSF de',
            'view_csf' => 'Ver CSF de',
            'adjust_stock' => 'Ajustar',
            'transfer_stock' => 'Trasladar',
            'download_inventory_template' => 'Descargar plantilla de',
            'import_inventory_template' => 'Importar conteo CSV de',
            'zero_untracked_stock' => 'Poner a cero existencias sin seguimiento de',
            'cash_count' => 'Conteo de caja',
            'open_shift' => 'Abrir turno',
            'close_shift' => 'Cerrar turno',
            'sale_shift' => 'Vender en turno',
            'discount' => 'Aplicar descuentos en',
            'refund' => 'Realizar devoluciones en',
            'configurar' => 'Configurar',
            'ver' => 'Ver',
            'ver_todas' => 'Ver todas las',
            'enviar_pdf' => 'Enviar PDF de',
        ];

        $parts = explode('.', $permission, 2);

        if (count($parts) !== 2) {
            return static::headline($permission);
        }

        [$module, $action] = $parts;

        $actionLabel = $actionLabels[$action] ?? static::headline($action);
        $moduleLabel = $moduleLabels[$module] ?? static::headline($module);

        return trim($actionLabel . ' ' . $moduleLabel);
    }

    protected static function headline(string $value): string
    {
        $value = str_replace(['_', '.', '-'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return mb_convert_case(trim((string) $value), MB_CASE_TITLE, 'UTF-8');
    }
}
