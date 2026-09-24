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

            'rrhh.empleados.ver' => 'Ver empleados',
            'rrhh.empleados.crear' => 'Crear empleados',
            'rrhh.empleados.editar' => 'Editar empleados',
            'rrhh.empleados.eliminar' => 'Eliminar empleados',

            'rrhh.credenciales.ver' => 'Ver credenciales QR',
            'rrhh.credenciales.descargar' => 'Descargar credenciales QR',

            'rrhh.organigrama.ver' => 'Ver organigrama',

            'rrhh.terminales.ver' => 'Ver terminales de asistencia',
            'rrhh.terminales.crear' => 'Crear terminales de asistencia',
            'rrhh.terminales.editar' => 'Editar terminales de asistencia',

            'rrhh.departamentos.ver' => 'Ver departamentos',
            'rrhh.departamentos.crear' => 'Crear departamentos',
            'rrhh.departamentos.editar' => 'Editar departamentos',
            'rrhh.departamentos.eliminar' => 'Eliminar departamentos',
            'rrhh.puestos.ver' => 'Ver puestos',
            'rrhh.puestos.crear' => 'Crear puestos',
            'rrhh.puestos.editar' => 'Editar puestos',
            'rrhh.puestos.eliminar' => 'Eliminar puestos',
            'rrhh.horarios.ver' => 'Ver horarios',
            'rrhh.horarios.crear' => 'Crear horarios',
            'rrhh.horarios.editar' => 'Editar horarios',
            'rrhh.horarios.eliminar' => 'Eliminar horarios',
            'rrhh.tipos_documento.ver' => 'Ver tipos de documento',
            'rrhh.tipos_documento.crear' => 'Crear tipos de documento',
            'rrhh.tipos_documento.editar' => 'Editar tipos de documento',
            'rrhh.tipos_documento.eliminar' => 'Eliminar tipos de documento',
            'rrhh.tipos_incidencia.ver' => 'Ver tipos de incidencia',
            'rrhh.tipos_incidencia.crear' => 'Crear tipos de incidencia',
            'rrhh.tipos_incidencia.editar' => 'Editar tipos de incidencia',
            'rrhh.tipos_incidencia.eliminar' => 'Eliminar tipos de incidencia',

            'rrhh.asistencias.ver' => 'Ver asistencias',
            'rrhh.asistencias.crear' => 'Crear asistencias',
            'rrhh.asistencias.editar' => 'Editar asistencias',
            'rrhh.asistencias.eliminar' => 'Eliminar asistencias',
            'rrhh.asistencias.revisar_movil' => 'Revisar asistencias móviles',
            'rrhh.asistencias.revisar_geocerca' => 'Revisar geocerca de asistencias',
            'rrhh.asistencias.mobile_clock' => 'Usar checador móvil',
            'rrhh.contratos.ver' => 'Ver contratos de empleados',
            'rrhh.contratos.crear' => 'Crear contratos de empleados',
            'rrhh.contratos.editar' => 'Editar contratos de empleados',
            'rrhh.contratos.eliminar' => 'Eliminar contratos de empleados',
            'rrhh.expediente.ver' => 'Ver expedientes de empleados',
            'rrhh.expediente.crear' => 'Crear documentos de expediente',
            'rrhh.expediente.editar' => 'Editar documentos de expediente',
            'rrhh.expediente.eliminar' => 'Eliminar documentos de expediente',
            'rrhh.incidencias.ver' => 'Ver incidencias',
            'rrhh.incidencias.crear' => 'Crear incidencias',
            'rrhh.incidencias.editar' => 'Editar incidencias',
            'rrhh.incidencias.eliminar' => 'Eliminar incidencias',
            'rrhh.vacaciones.ver' => 'Ver vacaciones y saldos',
            'rrhh.vacaciones.crear' => 'Crear saldos de vacaciones',
            'rrhh.vacaciones.editar' => 'Editar saldos de vacaciones',
            'rrhh.vacaciones.eliminar' => 'Eliminar saldos de vacaciones',
            'rrhh.bajas.ver' => 'Ver bajas de empleados',
            'rrhh.bajas.crear' => 'Crear bajas de empleados',
            'rrhh.bajas.editar' => 'Editar bajas de empleados',
            'rrhh.bajas.eliminar' => 'Eliminar bajas de empleados',
            'rrhh.geocercas.ver' => 'Ver geocercas de asistencia',
            'rrhh.geocercas.crear' => 'Crear geocercas de asistencia',
            'rrhh.geocercas.editar' => 'Editar geocercas de asistencia',
            'rrhh.geocercas.eliminar' => 'Eliminar geocercas de asistencia',

            'nomina.compras_via_nomina.ver' => 'Ver compras vía nómina',
            'nomina.compras_via_nomina.crear' => 'Crear compras vía nómina',
            'nomina.compras_via_nomina.editar' => 'Editar compras vía nómina',
            'nomina.compras_via_nomina.eliminar' => 'Eliminar compras vía nómina',
            'nomina.recibos_cfdi.ver' => 'Ver recibos CFDI de nómina',
            'nomina.procesos.ver' => 'Ver procesos de nómina',

            'catalogs.fiscal.view' => 'Ver catálogos fiscales',
            'catalogs.fiscal.create' => 'Crear catálogos fiscales',
            'catalogs.fiscal.update' => 'Editar catálogos fiscales',
            'catalogs.fiscal.delete' => 'Eliminar catálogos fiscales',

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

            'inventory.products.view' => 'Ver productos',
            'inventory.products.create' => 'Crear productos',
            'inventory.products.update' => 'Editar productos',
            'inventory.products.delete' => 'Eliminar productos',

            'inventory.product_categories.view' => 'Ver categorías de producto',
            'inventory.product_categories.create' => 'Crear categorías de producto',
            'inventory.product_categories.update' => 'Editar categorías de producto',

            'inventory.product_attributes.view' => 'Ver atributos de producto',
            'inventory.product_attributes.create' => 'Crear atributos de producto',
            'inventory.product_attributes.update' => 'Editar atributos de producto',
            'inventory.product_attributes.delete' => 'Eliminar atributos de producto',

            'inventory.stock.view' => 'Ver existencias',
            'inventory.stock.cost.view' => 'Ver costos de existencias',
            'inventory.as_of_date.view' => 'Ver inventario a fecha',
            'inventory.kardex.view' => 'Ver kardex por producto',
            'inventory.traceability.view' => 'Ver trazabilidad de inventario',
            'inventory.valuation.view' => 'Ver valorización de inventario',
            'inventory.costing_diagnostic.view' => 'Ver diagnóstico de costeo',
            'inventory.adjustments.view' => 'Ver ajustes de inventario',
            'inventory.adjustments.audit.view' => 'Ver auditoría de ajustes',
            'inventory.movements.view' => 'Ver movimientos de inventario',
            'inventory.warehouses.view' => 'Ver almacenes',
            'inventory.locations.view' => 'Ver ubicaciones',
            'inventory.location_types.view' => 'Ver tipos de ubicación',
            'inventory.operation_types.view' => 'Ver tipos de operación',
            'inventory.lots.view' => 'Ver lotes',
            'inventory.serials.view' => 'Ver números de serie',
            'inventory.serials.audit.view' => 'Ver auditoría de números de serie',

            'inventory.adjustments.delete' => 'Eliminar ajustes de inventario',

            'inventory.movements.create' => 'Crear movimientos de inventario',
            'inventory.movements.update' => 'Editar movimientos de inventario',
            'inventory.movements.delete' => 'Eliminar movimientos de inventario',

            'inventory.warehouses.create' => 'Crear almacenes',
            'inventory.warehouses.update' => 'Editar almacenes',
            'inventory.warehouses.delete' => 'Eliminar almacenes',

            'inventory.locations.create' => 'Crear ubicaciones',
            'inventory.locations.update' => 'Editar ubicaciones',
            'inventory.locations.delete' => 'Eliminar ubicaciones',

            'inventory.location_types.create' => 'Crear tipos de ubicación',
            'inventory.location_types.update' => 'Editar tipos de ubicación',
            'inventory.location_types.delete' => 'Eliminar tipos de ubicación',

            'inventory.operation_types.create' => 'Crear tipos de operación',
            'inventory.operation_types.update' => 'Editar tipos de operación',
            'inventory.operation_types.delete' => 'Eliminar tipos de operación',

            'inventory.lots.manage' => 'Administrar lotes',
            'inventory.serials.manage' => 'Administrar números de serie',

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
            'rrhh' => 'RRHH',
            'nomina' => 'nómina',
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
