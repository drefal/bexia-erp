@if(isset($line))
    @php
        $trackingDetailsColspan = $trackingDetailsColspan ?? 8;

        $decodeTrackingList = function ($value): array {
            if (is_array($value)) {
                return array_values($value);
            }

            if (is_string($value) && trim($value) !== '') {
                $decoded = json_decode($value, true);

                if (is_array($decoded)) {
                    return array_values($decoded);
                }

                return array_values(array_filter(array_map('trim', preg_split('/[\r\n,;]+/', $value) ?: [])));
            }

            return [];
        };

        $cleanTrackingValue = fn ($value): string => trim((string) ($value ?? ''));

        $fieldLabels = [
            'motor_number' => 'Motor',
            'customs_entry_number' => 'Pedimento',
            'customs_entry_date' => 'Fecha pedimento',
            'customs_office' => 'Aduana',
            'imported_model' => 'Modelo',
            'imported_color' => 'Color',
            'import_document_reference' => 'Referencia',
        ];

        $serialRowsFromReceipt = $decodeTrackingList($line->serial_import_rows ?? null);
        $serialNumbersFromReceipt = $decodeTrackingList($line->serial_numbers ?? null);

        if ($serialRowsFromReceipt === [] && $serialNumbersFromReceipt !== []) {
            $serialRowsFromReceipt = array_map(
                fn ($serial) => ['serial_number' => $serial],
                $serialNumbersFromReceipt
            );
        }

        $serialNumbersForFilter = [];

        foreach ($serialRowsFromReceipt as $serialRow) {
            if (is_array($serialRow) && $cleanTrackingValue($serialRow['serial_number'] ?? '') !== '') {
                $serialNumbersForFilter[] = mb_strtolower($cleanTrackingValue($serialRow['serial_number']));
            }
        }

        $stockSerialRows = collect();

        try {
            if (
                \Illuminate\Support\Facades\Schema::hasTable('stock_serial_numbers') &&
                \Illuminate\Support\Facades\Schema::hasColumn('stock_serial_numbers', 'serial_number')
            ) {
                $columns = [
                    'id',
                    'purchase_receipt_id',
                    'product_id',
                    'product_variant_id',
                    'serial_number',
                    'status',
                ];

                foreach (array_keys($fieldLabels) as $fieldName) {
                    if (\Illuminate\Support\Facades\Schema::hasColumn('stock_serial_numbers', $fieldName)) {
                        $columns[] = $fieldName;
                    }
                }

                $query = \Illuminate\Support\Facades\DB::table('stock_serial_numbers');

                if (
                    \Illuminate\Support\Facades\Schema::hasColumn('stock_serial_numbers', 'purchase_receipt_id') &&
                    ! empty($line->purchase_receipt_id)
                ) {
                    $query->where('purchase_receipt_id', $line->purchase_receipt_id);
                }

                if ($serialNumbersForFilter !== []) {
                    $query->whereIn(
                        \Illuminate\Support\Facades\DB::raw('LOWER(serial_number)'),
                        array_values(array_unique($serialNumbersForFilter))
                    );
                } else {
                    if (
                        \Illuminate\Support\Facades\Schema::hasColumn('stock_serial_numbers', 'product_id') &&
                        ! empty($line->product_id)
                    ) {
                        $query->where('product_id', $line->product_id);
                    }

                    if (
                        \Illuminate\Support\Facades\Schema::hasColumn('stock_serial_numbers', 'product_variant_id') &&
                        ! empty($line->product_variant_id)
                    ) {
                        $query->where(function ($variantQuery) use ($line): void {
                            $variantQuery
                                ->where('product_variant_id', $line->product_variant_id)
                                ->orWhereNull('product_variant_id');
                        });
                    }
                }

                $stockSerialRows = $query
                    ->orderBy('id')
                    ->get(array_values(array_unique($columns)));
            }
        } catch (\Throwable $e) {
            $stockSerialRows = collect();
        }

        $displaySerialRows = [];

        if ($stockSerialRows->isNotEmpty()) {
            foreach ($stockSerialRows as $stockSerial) {
                $displaySerialRows[] = [
                    'serial_number' => $stockSerial->serial_number ?? '',
                    'motor_number' => $stockSerial->motor_number ?? '',
                    'customs_entry_number' => $stockSerial->customs_entry_number ?? '',
                    'customs_entry_date' => $stockSerial->customs_entry_date ?? '',
                    'customs_office' => $stockSerial->customs_office ?? '',
                    'imported_model' => $stockSerial->imported_model ?? '',
                    'imported_color' => $stockSerial->imported_color ?? '',
                    'import_document_reference' => $stockSerial->import_document_reference ?? '',
                ];
            }
        } else {
            foreach ($serialRowsFromReceipt as $serialRow) {
                if (! is_array($serialRow)) {
                    continue;
                }

                $displaySerialRows[] = [
                    'serial_number' => $serialRow['serial_number'] ?? '',
                    'motor_number' => $serialRow['motor_number'] ?? ($line->motor_number ?? ''),
                    'customs_entry_number' => $serialRow['customs_entry_number'] ?? ($line->customs_entry_number ?? ''),
                    'customs_entry_date' => $serialRow['customs_entry_date'] ?? ($line->customs_entry_date ?? ''),
                    'customs_office' => $serialRow['customs_office'] ?? ($line->customs_office ?? ''),
                    'imported_model' => $serialRow['imported_model'] ?? ($line->imported_model ?? ''),
                    'imported_color' => $serialRow['imported_color'] ?? ($line->imported_color ?? ''),
                    'import_document_reference' => $serialRow['import_document_reference'] ?? ($line->import_document_reference ?? ''),
                ];
            }
        }

        $lineImportData = [];

        foreach ($fieldLabels as $fieldName => $label) {
            if ($cleanTrackingValue($line->{$fieldName} ?? '') !== '') {
                $lineImportData[$fieldName] = $line->{$fieldName};
            }
        }

        if ($cleanTrackingValue($line->lot_number ?? '') !== '') {
            $lineImportData = ['lot_number' => $line->lot_number] + $lineImportData;
        }

        $hasSerialDetails = false;

        foreach ($displaySerialRows as $row) {
            foreach (array_merge(['serial_number'], array_keys($fieldLabels)) as $fieldName) {
                if ($cleanTrackingValue($row[$fieldName] ?? '') !== '') {
                    $hasSerialDetails = true;
                    break 2;
                }
            }
        }

        $hasLineDetails = count($lineImportData) > 0;
        $hasTrackingDetail = $hasSerialDetails || $hasLineDetails;
    @endphp

    @if($hasTrackingDetail)
        <tr class="bexia-tracking-detail-row">
            <td colspan="{{ $trackingDetailsColspan }}" style="padding: 0 12px 14px 12px; border-bottom: 1px solid #e5e7eb;">
                <div style="border:1px solid #dbe3ef; border-radius:12px; background:#fbfdff; padding:12px; width:100%;">
                    <div style="font-weight:900; margin-bottom:8px;">
                        Detalle de trazabilidad / importación
                    </div>

                    @if($hasSerialDetails)
                        <div style="overflow-x:auto; width:100%;">
                            <table style="width:100%; min-width:1050px; border-collapse:collapse; font-size:12px;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left; padding:6px; border-bottom:1px solid #dbe3ef;">#</th>
                                        <th style="text-align:left; padding:6px; border-bottom:1px solid #dbe3ef;">Serie / VIN</th>
                                        <th style="text-align:left; padding:6px; border-bottom:1px solid #dbe3ef;">Motor</th>
                                        <th style="text-align:left; padding:6px; border-bottom:1px solid #dbe3ef;">Pedimento</th>
                                        <th style="text-align:left; padding:6px; border-bottom:1px solid #dbe3ef;">Fecha ped.</th>
                                        <th style="text-align:left; padding:6px; border-bottom:1px solid #dbe3ef;">Aduana</th>
                                        <th style="text-align:left; padding:6px; border-bottom:1px solid #dbe3ef;">Modelo</th>
                                        <th style="text-align:left; padding:6px; border-bottom:1px solid #dbe3ef;">Color</th>
                                        <th style="text-align:left; padding:6px; border-bottom:1px solid #dbe3ef;">Referencia</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($displaySerialRows as $row)
                                        <tr>
                                            <td style="padding:6px; border-bottom:1px solid #edf2f7; font-weight:800;">{{ $loop->iteration }}</td>
                                            <td style="padding:6px; border-bottom:1px solid #edf2f7; font-weight:800;">{{ $row['serial_number'] ?: '—' }}</td>
                                            <td style="padding:6px; border-bottom:1px solid #edf2f7;">{{ $row['motor_number'] ?: '—' }}</td>
                                            <td style="padding:6px; border-bottom:1px solid #edf2f7;">{{ $row['customs_entry_number'] ?: '—' }}</td>
                                            <td style="padding:6px; border-bottom:1px solid #edf2f7;">{{ $row['customs_entry_date'] ?: '—' }}</td>
                                            <td style="padding:6px; border-bottom:1px solid #edf2f7;">{{ $row['customs_office'] ?: '—' }}</td>
                                            <td style="padding:6px; border-bottom:1px solid #edf2f7;">{{ $row['imported_model'] ?: '—' }}</td>
                                            <td style="padding:6px; border-bottom:1px solid #edf2f7;">{{ $row['imported_color'] ?: '—' }}</td>
                                            <td style="padding:6px; border-bottom:1px solid #edf2f7;">{{ $row['import_document_reference'] ?: '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @elseif($hasLineDetails)
                        <div style="display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:8px; font-size:12px;">
                            @foreach($lineImportData as $fieldName => $value)
                                <div>
                                    <strong>{{ $fieldName === 'lot_number' ? 'Lote' : ($fieldLabels[$fieldName] ?? $fieldName) }}:</strong>
                                    {{ $value ?: '—' }}
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </td>
        </tr>
    @endif
@endif
