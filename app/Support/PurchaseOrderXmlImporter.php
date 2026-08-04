<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use SimpleXMLElement;

class PurchaseOrderXmlImporter
{
    public function import(string $absoluteXmlPath, int $companyId, ?int $warehouseId = null, ?int $locationId = null): int
    {
        if (! is_file($absoluteXmlPath)) {
            throw new RuntimeException('No se encontró el archivo XML.');
        }

        $xml = @simplexml_load_file($absoluteXmlPath);

        if (! $xml instanceof SimpleXMLElement) {
            throw new RuntimeException('El archivo no es un XML válido.');
        }

        $data = $this->parseCfdi($xml);

        if (empty($data['uuid'])) {
            throw new RuntimeException('El XML no contiene UUID fiscal.');
        }

        if (empty($data['concepts'])) {
            throw new RuntimeException('El XML no contiene conceptos.');
        }

        $this->validateDuplicateUuid($data['uuid'], $companyId);
        $this->validateCompanyRfc($companyId, $data['receiver_rfc'] ?? null);

        return DB::transaction(function () use ($data, $companyId, $warehouseId, $locationId, $absoluteXmlPath): int {
            $orderId = $this->createOrder($data, $companyId, $warehouseId, $locationId, $absoluteXmlPath);

            $pending = $this->createLines($orderId, $data['concepts']);

            $this->updateOrderTotalsAndStatus($orderId, $pending);

            return $orderId;
        });
    }

    protected function parseCfdi(SimpleXMLElement $xml): array
    {
        $namespaces = $xml->getNamespaces(true);
        $cfdiNs = $namespaces['cfdi'] ?? $xml->getName();

        $attributes = $this->attrs($xml);

        $children = $xml->children($cfdiNs);

        $emisor = isset($children->Emisor) ? $this->attrs($children->Emisor) : [];
        $receptor = isset($children->Receptor) ? $this->attrs($children->Receptor) : [];

        $uuid = null;

        foreach ($namespaces as $prefix => $ns) {
            if (str_contains(strtolower($ns), 'timbrefiscaldigital')) {
                $complemento = $children->Complemento ?? null;

                if ($complemento) {
                    $tfdChildren = $complemento->children($ns);

                    if (isset($tfdChildren->TimbreFiscalDigital)) {
                        $tfd = $this->attrs($tfdChildren->TimbreFiscalDigital);
                        $uuid = $tfd['UUID'] ?? null;
                    }
                }
            }
        }

        $concepts = [];

        if (isset($children->Conceptos)) {
            foreach ($children->Conceptos->children($cfdiNs)->Concepto as $index => $concepto) {
                $conceptAttrs = $this->attrs($concepto);

                $taxAmount = 0.0;
                $taxRate = 0.0;

                $conceptChildren = $concepto->children($cfdiNs);

                if (isset($conceptChildren->Impuestos)) {
                    $impChildren = $conceptChildren->Impuestos->children($cfdiNs);

                    if (isset($impChildren->Traslados)) {
                        foreach ($impChildren->Traslados->children($cfdiNs)->Traslado as $traslado) {
                            $trasladoAttrs = $this->attrs($traslado);

                            if (($trasladoAttrs['Impuesto'] ?? '') === '002') {
                                $taxAmount += (float) ($trasladoAttrs['Importe'] ?? 0);
                                $taxRate = max($taxRate, ((float) ($trasladoAttrs['TasaOCuota'] ?? 0)) * 100);
                            }
                        }
                    }
                }

                $quantity = (float) ($conceptAttrs['Cantidad'] ?? 0);
                $unitValue = (float) ($conceptAttrs['ValorUnitario'] ?? 0);
                $amount = (float) ($conceptAttrs['Importe'] ?? ($quantity * $unitValue));

                $concepts[] = [
                    'index' => ((int) $index) + 1,
                    'no_identificacion' => trim((string) ($conceptAttrs['NoIdentificacion'] ?? '')),
                    'description' => trim((string) ($conceptAttrs['Descripcion'] ?? '')),
                    'unit_key' => trim((string) ($conceptAttrs['ClaveUnidad'] ?? '')),
                    'unit_name' => trim((string) ($conceptAttrs['Unidad'] ?? '')),
                    'quantity' => $quantity,
                    'unit_value' => $unitValue,
                    'amount' => $amount,
                    'tax_amount' => $taxAmount,
                    'tax_rate' => $taxRate,
                    'total' => $amount + $taxAmount,
                ];
            }
        }

        return [
            'uuid' => trim((string) $uuid),
            'version' => (string) ($attributes['Version'] ?? $attributes['version'] ?? ''),
            'date' => (string) ($attributes['Fecha'] ?? ''),
            'currency' => (string) ($attributes['Moneda'] ?? ''),
            'subtotal' => (float) ($attributes['SubTotal'] ?? 0),
            'total' => (float) ($attributes['Total'] ?? 0),
            'supplier_rfc' => (string) ($emisor['Rfc'] ?? $emisor['RFC'] ?? ''),
            'supplier_name' => (string) ($emisor['Nombre'] ?? 'Proveedor XML'),
            'receiver_rfc' => (string) ($receptor['Rfc'] ?? $receptor['RFC'] ?? ''),
            'concepts' => $concepts,
        ];
    }

    protected function attrs(SimpleXMLElement $node): array
    {
        $result = [];

        foreach ($node->attributes() as $key => $value) {
            $result[(string) $key] = (string) $value;
        }

        return $result;
    }

    protected function validateDuplicateUuid(string $uuid, int $companyId): void
    {
        if (! Schema::hasColumn('purchase_orders', 'xml_uuid')) {
            return;
        }

        $query = DB::table('purchase_orders')
            ->where('xml_uuid', $uuid);

        /*
         * El mismo XML no debe repetirse dentro de la misma empresa.
         * Pero sí puede existir en otra empresa, por ejemplo si se cargó por error en otra compañía.
         */
        if ($companyId > 0 && Schema::hasColumn('purchase_orders', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        if ($query->exists()) {
            throw new RuntimeException('Este XML ya fue importado previamente en esta empresa. UUID: ' . $uuid);
        }
    }

    protected function validateCompanyRfc(int $companyId, ?string $receiverRfc): void
    {
        $receiverRfc = strtoupper(trim((string) $receiverRfc));

        if ($companyId <= 0 || $receiverRfc === '' || ! Schema::hasTable('companies')) {
            return;
        }

        $company = DB::table('companies')->where('id', $companyId)->first();

        if (! $company) {
            return;
        }

        foreach (['rfc', 'tax_id', 'fiscal_rfc', 'company_rfc'] as $column) {
            if (! property_exists($company, $column)) {
                continue;
            }

            $companyRfc = strtoupper(trim((string) ($company->{$column} ?? '')));

            if ($companyRfc !== '' && $companyRfc !== $receiverRfc) {
                throw new RuntimeException('El RFC receptor del XML no coincide con la empresa actual. XML: ' . $receiverRfc . ', Empresa: ' . $companyRfc);
            }

            if ($companyRfc !== '') {
                return;
            }
        }
    }


    protected function findOrCreateSupplierContact(array $data, int $companyId): ?object
    {
        if (! Schema::hasTable('contacts')) {
            return null;
        }

        $columns = Schema::getColumnListing('contacts');

        $rfc = strtoupper(trim((string) ($data['supplier_rfc'] ?? '')));
        $name = trim((string) ($data['supplier_name'] ?? ''));

        if ($rfc === '' && $name === '') {
            return null;
        }

        $nameColumn = $this->firstExistingColumn($columns, [
            'name',
            'legal_name',
            'business_name',
            'company_name',
            'display_name',
        ]);

        $rfcColumn = $this->firstExistingColumn($columns, [
            'rfc',
            'tax_id',
            'fiscal_rfc',
            'vat',
            'vat_number',
        ]);

        if (! $nameColumn) {
            return null;
        }

        $baseQuery = DB::table('contacts');

        if ($companyId > 0 && in_array('company_id', $columns, true)) {
            $baseQuery->where('company_id', $companyId);
        }

        $existing = null;

        if ($rfc !== '' && $rfcColumn) {
            $existing = (clone $baseQuery)
                ->whereRaw('UPPER(' . $rfcColumn . ') = ?', [$rfc])
                ->first();
        }

        if (! $existing && $name !== '') {
            $existing = (clone $baseQuery)
                ->whereRaw('LOWER(' . $nameColumn . ') = ?', [mb_strtolower($name)])
                ->first();
        }

        if ($existing) {
            $updates = [];

            foreach (['is_supplier', 'supplier', 'is_vendor', 'vendor'] as $column) {
                if (in_array($column, $columns, true)) {
                    $updates[$column] = true;
                }
            }

            foreach (['type', 'contact_type', 'kind'] as $column) {
                if (in_array($column, $columns, true) && empty($existing->{$column})) {
                    $updates[$column] = 'supplier';
                }
            }

            if ($rfc !== '' && $rfcColumn && empty($existing->{$rfcColumn})) {
                $updates[$rfcColumn] = $rfc;
            }

            if ($name !== '' && empty($existing->{$nameColumn})) {
                $updates[$nameColumn] = $name;
            }

            if (in_array('updated_at', $columns, true)) {
                $updates['updated_at'] = now();
            }

            if ($updates) {
                DB::table('contacts')->where('id', $existing->id)->update($updates);
            }

            return DB::table('contacts')->where('id', $existing->id)->first();
        }

        $insert = [];

        if ($companyId > 0 && in_array('company_id', $columns, true)) {
            $insert['company_id'] = $companyId;
        }

        $insert[$nameColumn] = $name !== '' ? $name : ('Proveedor ' . $rfc);

        if ($rfc !== '' && $rfcColumn) {
            $insert[$rfcColumn] = $rfc;
        }

        foreach (['is_supplier', 'supplier', 'is_vendor', 'vendor'] as $column) {
            if (in_array($column, $columns, true)) {
                $insert[$column] = true;
            }
        }

        foreach (['is_customer', 'customer', 'is_client', 'client'] as $column) {
            if (in_array($column, $columns, true)) {
                $insert[$column] = false;
            }
        }

        foreach (['is_active', 'active'] as $column) {
            if (in_array($column, $columns, true)) {
                $insert[$column] = true;
            }
        }

        foreach (['type', 'contact_type', 'kind'] as $column) {
            if (in_array($column, $columns, true)) {
                $insert[$column] = 'supplier';
            }
        }

        if (in_array('origin', $columns, true)) {
            $insert['origin'] = 'xml';
        }

        if (in_array('notes', $columns, true)) {
            $insert['notes'] = 'Proveedor creado automáticamente desde XML CFDI.';
        }

        if (in_array('created_at', $columns, true)) {
            $insert['created_at'] = now();
        }

        if (in_array('updated_at', $columns, true)) {
            $insert['updated_at'] = now();
        }

        try {
            $id = DB::table('contacts')->insertGetId($insert);

            return DB::table('contacts')->where('id', $id)->first();
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    protected function contactDisplayName(?object $contact): ?string
    {
        if (! $contact) {
            return null;
        }

        foreach (['name', 'legal_name', 'business_name', 'company_name', 'display_name'] as $column) {
            if (property_exists($contact, $column) && trim((string) $contact->{$column}) !== '') {
                return trim((string) $contact->{$column});
            }
        }

        return null;
    }

    protected function firstExistingColumn(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function createOrder(array $data, int $companyId, ?int $warehouseId, ?int $locationId, string $absoluteXmlPath): int
    {
        $columns = Schema::getColumnListing('purchase_orders');
        $supplierContact = $this->findOrCreateSupplierContact($data, $companyId);
        $supplierContact = $this->findOrCreateSupplierContact($data, $companyId);
        $supplierContact = $this->findOrCreateSupplierContact($data, $companyId);

        $order = [];

        $this->set($order, $columns, 'company_id', $companyId);
        $this->set($order, $columns, 'number', $this->nextPurchaseOrderNumber($companyId));
        $this->set($order, $columns, 'status', 'draft');
        $this->set($order, $columns, 'origin', 'XML CFDI ' . $data['uuid']);
        $this->set($order, $columns, 'supplier_name', $this->contactDisplayName($supplierContact) ?: ($data['supplier_name'] ?: 'Proveedor XML'));
        $this->set($order, $columns, 'supplier_id', $supplierContact?->id);
        $this->set($order, $columns, 'contact_id', $supplierContact?->id);
        $this->set($order, $columns, 'provider_id', $supplierContact?->id);
        $warehouseLabel = null;
        $locationLabel = null;

        if ($warehouseId) {
            if (! Schema::hasTable('warehouses')) {
                throw new RuntimeException('No existe el catalogo de almacenes.');
            }

            $warehouseColumns = Schema::getColumnListing('warehouses');
            $warehouseQuery = DB::table('warehouses')->where('id', $warehouseId);

            if (in_array('company_id', $warehouseColumns, true)) {
                $warehouseQuery->where('company_id', $companyId);
            }

            if (in_array('is_active', $warehouseColumns, true)) {
                $warehouseQuery->where('is_active', true);
            }

            $warehouse = $warehouseQuery->first();

            if (! $warehouse) {
                throw new RuntimeException('El almacen seleccionado no pertenece a la empresa actual o esta inactivo.');
            }

            $warehouseLabel = trim(implode(' - ', array_values(array_filter([
                trim((string) ($warehouse->code ?? '')),
                trim((string) ($warehouse->name ?? '')),
            ], static fn (string $value): bool => $value !== ''))));
        }

        if ($locationId) {
            if (! $warehouseId) {
                throw new RuntimeException('Selecciona un almacen antes de la ubicacion de recepcion.');
            }

            if (! Schema::hasTable('stock_locations')) {
                throw new RuntimeException('No existe el catalogo de ubicaciones.');
            }

            $locationColumns = Schema::getColumnListing('stock_locations');
            $locationQuery = DB::table('stock_locations')->where('id', $locationId);

            if (in_array('company_id', $locationColumns, true)) {
                $locationQuery->where('company_id', $companyId);
            }

            if (in_array('warehouse_id', $locationColumns, true)) {
                $locationQuery->where('warehouse_id', $warehouseId);
            }

            if (in_array('is_active', $locationColumns, true)) {
                $locationQuery->where('is_active', true);
            }

            $location = $locationQuery->first();

            if (! $location) {
                throw new RuntimeException('La ubicacion seleccionada no pertenece al almacen y empresa actuales o esta inactiva.');
            }

            $locationLabel = trim(implode(' - ', array_values(array_filter([
                trim((string) ($location->code ?? '')),
                trim((string) ($location->name ?? '')),
            ], static fn (string $value): bool => $value !== ''))));
        }

        $this->set($order, $columns, 'warehouse_id', $warehouseId);
        $this->set($order, $columns, 'location_id', $locationId);
        $this->set($order, $columns, 'warehouse_label', $warehouseLabel);
        $this->set($order, $columns, 'location_label', $locationLabel);
        $this->set($order, $columns, 'order_date', now());
        $this->set($order, $columns, 'date', now());
        $this->set($order, $columns, 'created_from_xml', true);
        $this->set($order, $columns, 'xml_uuid', $data['uuid']);
        $this->set($order, $columns, 'xml_supplier_rfc', $data['supplier_rfc']);
        $this->set($order, $columns, 'xml_supplier_name', $data['supplier_name']);
        $this->set($order, $columns, 'xml_receiver_rfc', $data['receiver_rfc']);
        $this->set($order, $columns, 'xml_issued_at', $data['date'] ? date('Y-m-d H:i:s', strtotime($data['date'])) : null);
        $this->set($order, $columns, 'xml_currency', $data['currency']);
        $this->set($order, $columns, 'xml_subtotal', $data['subtotal']);
        $this->set($order, $columns, 'xml_total', $data['total']);
        $this->set($order, $columns, 'xml_path', $absoluteXmlPath);
        $this->set($order, $columns, 'xml_import_status', 'pending_mapping');
        $this->set($order, $columns, 'notes', 'OC creada desde XML CFDI UUID ' . $data['uuid']);
        $this->set($order, $columns, 'created_at', now());
        $this->set($order, $columns, 'updated_at', now());

        return DB::table('purchase_orders')->insertGetId($order);
    }

    protected function createLines(int $orderId, array $concepts): int
    {
        if (! Schema::hasTable('purchase_order_lines')) {
            return 0;
        }

        $columns = Schema::getColumnListing('purchase_order_lines');
        $pending = 0;

        foreach ($concepts as $concept) {
            $match = $this->findProductMatch($concept);
            $requiresMapping = ! $match;

            if ($requiresMapping) {
                $pending++;
            }

            $line = [];

            $this->set($line, $columns, 'purchase_order_id', $orderId);

            if ($match) {
                $this->set($line, $columns, 'product_id', $match['product_id'] ?? null);
                $this->set($line, $columns, 'product_variant_id', $match['variant_id'] ?? null);
                $this->set($line, $columns, 'variant_id', $match['variant_id'] ?? null);
                $this->set($line, $columns, 'product_label', $match['product_label'] ?? $concept['description']);
                $this->set($line, $columns, 'variant_label', $match['variant_label'] ?? '—');
            } else {
                $this->set($line, $columns, 'product_label', 'PENDIENTE - ' . $concept['description']);
                $this->set($line, $columns, 'variant_label', 'Requiere asignar producto');
            }

            $unitLabel = $concept['unit_name'] ?: $concept['unit_key'] ?: 'Unidad';

            $this->set($line, $columns, 'purchase_unit_label', $unitLabel);
            $this->set($line, $columns, 'purchase_unit_factor', 1);
            $this->set($line, $columns, 'ordered_quantity', $concept['quantity']);
            $this->set($line, $columns, 'requested_quantity', $concept['quantity']);
            $this->set($line, $columns, 'base_quantity', $concept['quantity']);
            $this->set($line, $columns, 'sat_unit_key', $concept['unit_key']);
            $this->set($line, $columns, 'sat_unit_name', $concept['unit_name']);
            $this->set($line, $columns, 'unit_cost_without_tax', $concept['unit_value']);
            $this->set($line, $columns, 'tax_rate', $concept['tax_rate']);
            $this->set($line, $columns, 'line_total_without_tax', $concept['amount']);
            $this->set($line, $columns, 'line_tax', $concept['tax_amount']);
            $this->set($line, $columns, 'line_total_with_tax', $concept['total']);

            $this->set($line, $columns, 'xml_line_index', $concept['index']);
            $this->set($line, $columns, 'xml_no_identificacion', $concept['no_identificacion']);
            $this->set($line, $columns, 'xml_description', $concept['description']);
            $this->set($line, $columns, 'xml_unit_key', $concept['unit_key']);
            $this->set($line, $columns, 'xml_unit_name', $concept['unit_name']);
            $this->set($line, $columns, 'xml_requires_mapping', $requiresMapping);
            $this->set($line, $columns, 'xml_mapping_status', $requiresMapping ? 'pending' : 'matched');
            $this->set($line, $columns, 'created_at', now());
            $this->set($line, $columns, 'updated_at', now());

            DB::table('purchase_order_lines')->insert($line);
        }

        return $pending;
    }

    protected function updateOrderTotalsAndStatus(int $orderId, int $pending): void
    {
        $orderColumns = Schema::getColumnListing('purchase_orders');

        $totals = DB::table('purchase_order_lines')
            ->where('purchase_order_id', $orderId)
            ->selectRaw('
                COALESCE(SUM(line_total_without_tax), 0) as subtotal,
                COALESCE(SUM(line_tax), 0) as tax,
                COALESCE(SUM(line_total_with_tax), 0) as total
            ')
            ->first();

        $updates = [];

        $this->set($updates, $orderColumns, 'total_without_tax', (float) ($totals->subtotal ?? 0));
        $this->set($updates, $orderColumns, 'total_tax', (float) ($totals->tax ?? 0));
        $this->set($updates, $orderColumns, 'total_with_tax', (float) ($totals->total ?? 0));
        $this->set($updates, $orderColumns, 'xml_mapping_pending_count', $pending);
        $this->set($updates, $orderColumns, 'xml_import_status', $pending > 0 ? 'pending_mapping' : 'mapped');
        $this->set($updates, $orderColumns, 'updated_at', now());

        DB::table('purchase_orders')->where('id', $orderId)->update($updates);
    }

    protected function findProductMatch(array $concept): ?array
    {
        $code = trim((string) ($concept['no_identificacion'] ?? ''));
        $description = trim((string) ($concept['description'] ?? ''));

        if ($code !== '') {
            $variant = $this->findInTable('product_variants', $code, ['sku', 'code', 'barcode', 'no_identificacion', 'no_identification', 'supplier_code']);

            if ($variant) {
                $productId = (int) ($variant->product_id ?? 0);
                $product = $productId > 0 && Schema::hasTable('products')
                    ? DB::table('products')->where('id', $productId)->first()
                    : null;

                return [
                    'product_id' => $productId ?: null,
                    'variant_id' => (int) ($variant->id ?? 0),
                    'product_label' => $this->label($product, ['sku', 'code'], ['name', 'description']) ?: $description,
                    'variant_label' => $this->label($variant, ['sku', 'code'], ['name', 'description']) ?: '—',
                ];
            }

            $product = $this->findInTable('products', $code, ['sku', 'code', 'barcode', 'no_identificacion', 'no_identification', 'supplier_code']);

            if ($product) {
                return [
                    'product_id' => (int) $product->id,
                    'variant_id' => null,
                    'product_label' => $this->label($product, ['sku', 'code'], ['name', 'description']) ?: $description,
                    'variant_label' => '—',
                ];
            }
        }

        return null;
    }

    protected function findInTable(string $table, string $value, array $columns): ?object
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        $existing = Schema::getColumnListing($table);
        $value = trim($value);

        foreach ($columns as $column) {
            if (! in_array($column, $existing, true)) {
                continue;
            }

            $row = DB::table($table)
                ->whereRaw('LOWER(' . $column . ') = ?', [mb_strtolower($value)])
                ->first();

            if ($row) {
                return $row;
            }
        }

        return null;
    }

    protected function label(?object $record, array $codeColumns, array $nameColumns): ?string
    {
        if (! $record) {
            return null;
        }

        $code = null;
        $name = null;

        foreach ($codeColumns as $column) {
            if (property_exists($record, $column) && trim((string) $record->{$column}) !== '') {
                $code = trim((string) $record->{$column});
                break;
            }
        }

        foreach ($nameColumns as $column) {
            if (property_exists($record, $column) && trim((string) $record->{$column}) !== '') {
                $name = trim((string) $record->{$column});
                break;
            }
        }

        return trim(($code ? $code . ' - ' : '') . ($name ?: ''));
    }

    protected function nextPurchaseOrderNumber(int $companyId): string
    {
        $prefix = 'OC-' . now()->format('Ymd') . '-';

        $query = DB::table('purchase_orders')
            ->where('number', 'like', $prefix . '%');

        if ($companyId > 0 && Schema::hasColumn('purchase_orders', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        $last = $query->orderByDesc('number')->value('number');
        $next = 1;

        if ($last && preg_match('/-(\d+)$/', (string) $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    protected function set(array &$array, array $columns, string $column, mixed $value): void
    {
        if (in_array($column, $columns, true)) {
            $array[$column] = $value;
        }
    }
}
