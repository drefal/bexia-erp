<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Factura {{ $invoice->cfdi_series ?? '' }} {{ $invoice->cfdi_folio ?? '' }}</title>
    <style>
        /* BEXIA_V5523Q3_PDF_VISUAL_TUNE */
        @page { margin: 16px 18px 14px 18px; }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111;
            font-size: 9px;
            line-height: 1.15;
        }

        .clearfix:after {
            content: "";
            display: block;
            clear: both;
        }

        .right { text-align: right; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .muted { color: #666; }
        .upper { text-transform: uppercase; }
        .mono { font-family: DejaVu Sans Mono, monospace; word-break: break-all; }
        .tiny { font-size: 7px; }

        .preliminar {
            position: fixed;
            top: 340px;
            left: 55px;
            right: 55px;
            text-align: center;
            font-size: 44px;
            color: #eeeeee;
            transform: rotate(-24deg);
            z-index: -1;
            font-weight: bold;
        }

        .brand-header {
            border-bottom: 2px solid #1f2937;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }

        .logo-col {
            float: left;
            width: 28%;
            text-align: center;
            min-height: 72px;
        }

        .logo-col img {
            max-width: 135px;
            max-height: 58px;
        }

        .company-col {
            float: left;
            width: 48%;
            text-align: center;
        }

        .branch-col {
            float: right;
            width: 22%;
            font-size: 9px;
            padding-top: 28px;
        }

        .company-title {
            font-size: 15.8px;
            font-weight: 800;
            letter-spacing: .35px;
            margin-bottom: 2px;
        }

        .company-sub {
            font-size: 8.5px;
            font-weight: 600;
        }

        .company-address {
            margin-top: 5px;
            font-size: 8.5px;
            line-height: 1.1;
            white-space: pre-line;
            text-align: left;
            display: inline-block;
            min-width: 210px;
        }

        .branch-title {
            font-size: 9.5px;
            font-weight: 800;
        }

        .header-row {
            margin-bottom: 8px;
        }

        .client-block {
            float: left;
            width: 48%;
            font-size: 8.7px;
        }

        .meta-block {
            float: right;
            width: 48%;
        }

        .client-title {
            color: #666;
            font-size: 9px;
            margin-bottom: 2px;
        }

        .client-name {
            font-size: 9.5px;
            font-weight: 800;
        }

        .address {
            white-space: pre-line;
            line-height: 1.18;
        }

        .kv-line {
            margin-top: 4px;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5px;
        }

        .meta-table td {
            border: 1px solid #444;
            padding: 2px 4px;
        }

        .meta-table td:first-child {
            width: 34%;
            font-weight: 700;
        }

        .meta-table td:last-child {
            text-align: right;
        }

        table.lines {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            font-size: 7.4px;
        }

        .lines th {
            border: 1px solid #c7c7c7;
            border-bottom: 2px solid #111;
            background: #fff;
            text-align: center;
            padding: 2px 3px;
            font-weight: 800;
            line-height: 1.05;
        }

        .lines td {
            border: 1px solid #d6d6d6;
            padding: 2px 3px;
            vertical-align: top;
            text-align: center;
            line-height: 1.1;
        }

        .lines .desc {
            text-align: center;
            text-transform: uppercase;
        }

        .totals-wrap {
            width: 48%;
            margin-left: 52%;
            margin-top: 8px;
        }

        .totals {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }

        .totals td {
            border: 1px solid #d6d6d6;
            padding: 5px 6px;
        }

        .totals td:first-child {
            width: 58%;
            text-align: right;
            font-weight: 800;
        }

        .totals td:last-child {
            text-align: right;
        }

        .amount-words {
            text-align: center;
            margin-top: 8px;
            margin-bottom: 4px;
        }

        .amount-words .words {
            font-size: 8.6px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .amount-words .legend {
            margin-top: 3px;
            font-size: 8px;
            color: #555;
        }

        .fiscal-wrap {
            margin-top: 5px;
            page-break-inside: avoid;
        }

        .qr-col {
            float: left;
            width: 27%;
        }

        .qr-col img {
            width: 158px;
            height: 158px;
            border: 0;
        }

        .qr-placeholder {
            width: 158px;
            height: 158px;
            border: 1px solid #ccc;
            text-align: center;
            font-size: 8.5px;
            color: #666;
            padding-top: 68px;
            box-sizing: border-box;
        }

        .fiscal-col {
            float: right;
            width: 71%;
        }

        .band {
            background: #5f5f5f;
            color: #fff;
            font-weight: 800;
            padding: 2.5px 5px;
            margin-top: 2px;
            border-radius: 2px;
            font-size: 7.5px;
        }

        .seal {
            font-size: 5.75px;
            line-height: 1.06;
            word-break: break-all;
            margin-bottom: 1px;
        }

        .info-extra {
            font-size: 5.85px;
            line-height: 1.08;
            word-break: break-word;
        }
    
        /* BEXIA_V5523Q6_HEADER_LOGO_PRO_START */
        /*
         * Ajuste visual Q6:
         * - Logo más grande.
         * - Cabecera más balanceada.
         * - Emisor más legible.
         * - Sucursal compacta.
         */
        .brand-header {
            padding-bottom: 10px !important;
            margin-bottom: 8px !important;
            min-height: 92px !important;
            border-bottom: 2px solid #1f2937 !important;
        }

        .logo-col {
            float: left !important;
            width: 36% !important;
            text-align: left !important;
            min-height: 92px !important;
            padding-top: 0 !important;
        }

        .logo-col img {
            max-width: 285px !important;
            max-height: 112px !important;
            width: auto !important;
            height: auto !important;
            display: block !important;
            margin: 0 !important;
        }

        .company-col {
            float: left !important;
            width: 40% !important;
            text-align: center !important;
            padding-top: 8px !important;
        }

        .branch-col {
            float: right !important;
            width: 22% !important;
            padding-top: 23px !important;
            font-size: 8.5px !important;
            text-align: left !important;
        }

        .company-title {
            font-size: 17.4px !important;
            font-weight: 800 !important;
            letter-spacing: .35px !important;
            margin-bottom: 3px !important;
            line-height: 1.08 !important;
        }

        .company-sub {
            font-size: 8.4px !important;
            font-weight: 700 !important;
            line-height: 1.1 !important;
        }

        .company-address {
            margin-top: 3px !important;
            font-size: 7.8px !important;
            line-height: 1.05 !important;
            white-space: pre-line !important;
            text-align: center !important;
            display: block !important;
            min-width: 0 !important;
        }

        .branch-title {
            font-size: 9px !important;
            font-weight: 800 !important;
            margin-bottom: 2px !important;
        }

        .header-row {
            margin-top: 4px !important;
            margin-bottom: 8px !important;
        }

        .client-block {
            width: 47% !important;
        }

        .meta-block {
            width: 49.5% !important;
        }

        .meta-table {
            font-size: 8.2px !important;
        }

        .meta-table td {
            padding: 2.5px 4px !important;
        }

        table.lines {
            margin-top: 6px !important;
        }

        .amount-words {
            margin-top: 8px !important;
        }

        .qr-col img {
            width: 165px !important;
            height: 165px !important;
        }

        .qr-placeholder {
            width: 165px !important;
            height: 165px !important;
            padding-top: 70px !important;
        }
        /* BEXIA_V5523Q6_HEADER_LOGO_PRO_END */

    
        /* BEXIA_V5523Q7B_MINIMAL_SEAL_OVERFLOW_START */
        /*
         * Fix mínimo:
         * mantiene la tipografía actual y solo evita que sellos/cadena se salgan a la derecha.
         */
        .seal,
        .info-extra {
            max-width: 100% !important;
            box-sizing: border-box !important;
            overflow: hidden !important;
            white-space: normal !important;
            word-break: normal !important;
            overflow-wrap: break-word !important;
        }
        /* BEXIA_V5523Q7B_MINIMAL_SEAL_OVERFLOW_END */

    </style>
</head>
<body>

@php
    /*
     * BEXIA_V5523T2_PDF_BRANCH_FROM_BILLING_SERIES
     * Sucursal PDF:
     * invoices.billing_series_id -> billing_series.branch_id -> branches.id
     * Si no encuentra sucursal, usa empresa.
     */

    $__pdfInvoice = $invoice ?? ($record ?? null);

    $__pdfRegimenFiscalMapT2 = [
        '601' => 'General de Ley Personas Morales',
        '603' => 'Personas Morales con Fines no Lucrativos',
        '605' => 'Sueldos y Salarios e Ingresos Asimilados a Salarios',
        '606' => 'Arrendamiento',
        '607' => 'Régimen de Enajenación o Adquisición de Bienes',
        '608' => 'Demás ingresos',
        '610' => 'Residentes en el Extranjero sin Establecimiento Permanente en México',
        '611' => 'Ingresos por Dividendos (socios y accionistas)',
        '612' => 'Personas Físicas con Actividades Empresariales y Profesionales',
        '614' => 'Ingresos por intereses',
        '615' => 'Régimen de los ingresos por obtención de premios',
        '616' => 'Sin obligaciones fiscales',
        '620' => 'Sociedades Cooperativas de Producción que optan por diferir sus ingresos',
        '621' => 'Incorporación Fiscal',
        '622' => 'Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras',
        '623' => 'Opcional para Grupos de Sociedades',
        '624' => 'Coordinados',
        '625' => 'Régimen de las Actividades Empresariales con ingresos a través de Plataformas Tecnológicas',
        '626' => 'Régimen Simplificado de Confianza',
    ];

    $__pdfFormatRegimenT2 = function ($code) use ($__pdfRegimenFiscalMapT2): string {
        $code = trim((string) $code);

        if ($code === '') {
            return '';
        }

        return $code.' - '.($__pdfRegimenFiscalMapT2[$code] ?? 'Régimen fiscal SAT');
    };

    $__pdfRowNameT2 = function (string $table, int $id): string {
        if ($id <= 0 || ! \Illuminate\Support\Facades\Schema::hasTable($table)) {
            return '';
        }

        $row = \Illuminate\Support\Facades\DB::table($table)->where('id', $id)->first();

        if (! $row) {
            return '';
        }

        foreach ([
            'commercial_name',
            'display_name',
            'name',
            'branch_name',
            'sucursal_name',
            'store_name',
            'location_name',
            'warehouse_name',
            'description',
            'city',
        ] as $field) {
            $value = trim((string) ($row->{$field} ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    };

    $__pdfCompanyNameT2 = function () use ($__pdfInvoice, $__pdfRowNameT2): string {
        $companyId = (int) data_get($__pdfInvoice, 'company_id');

        if ($companyId > 0) {
            $name = $__pdfRowNameT2('companies', $companyId);

            if ($name !== '') {
                return $name;
            }
        }

        foreach ([
            'issuer_name',
            'issuer_fiscal_name',
            'company_name',
            'company_fiscal_name',
        ] as $field) {
            $value = trim((string) data_get($__pdfInvoice, $field));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    };

    $__pdfBranchNameT2 = '';

    /*
     * 1) Ruta comprobada en PROD:
     * invoice.billing_series_id=2 -> billing_series.branch_id=1 -> branches.id=1
     */
    $billingSeriesIdT2 = (int) data_get($__pdfInvoice, 'billing_series_id');

    if ($billingSeriesIdT2 > 0 && \Illuminate\Support\Facades\Schema::hasTable('billing_series')) {
        $seriesT2 = \Illuminate\Support\Facades\DB::table('billing_series')->where('id', $billingSeriesIdT2)->first();

        if ($seriesT2) {
            $branchIdT2 = (int) ($seriesT2->branch_id ?? 0);

            if ($branchIdT2 > 0) {
                $__pdfBranchNameT2 = $__pdfRowNameT2('branches', $branchIdT2);
            }

            if ($__pdfBranchNameT2 === '') {
                foreach (['branch_name', 'sucursal_name', 'store_name', 'location_name', 'warehouse_name'] as $field) {
                    $value = trim((string) ($seriesT2->{$field} ?? ''));

                    if ($value !== '') {
                        $__pdfBranchNameT2 = $value;
                        break;
                    }
                }
            }
        }
    }

    /*
     * 2) Fallbacks directos de invoice.
     */
    if ($__pdfBranchNameT2 === '') {
        foreach ([
            'branch_id' => 'branches',
            'sucursal_id' => 'branches',
            'warehouse_id' => 'warehouses',
        ] as $field => $table) {
            $id = (int) data_get($__pdfInvoice, $field);

            if ($id > 0) {
                $__pdfBranchNameT2 = $__pdfRowNameT2($table, $id);

                if ($__pdfBranchNameT2 !== '') {
                    break;
                }
            }
        }
    }

    /*
     * 3) Nunca usar "Matriz" como fallback final si no es sucursal real.
     */
    if ($__pdfBranchNameT2 === '' || mb_strtolower($__pdfBranchNameT2) === 'matriz') {
        $__pdfBranchNameT2 = $__pdfCompanyNameT2();
    }

    $__pdfIssuerRegimenCodeT2 = trim((string) (
        data_get($__pdfInvoice, 'issuer_fiscal_regime')
        ?: data_get($__pdfInvoice, 'company_fiscal_regime')
        ?: data_get($__pdfInvoice, 'fiscal_regime')
        ?: data_get($__pdfInvoice, 'regimen_fiscal')
        ?: data_get($issuer ?? [], 'fiscal_regime')
        ?: data_get($issuer ?? [], 'regimen_fiscal')
        ?: data_get($company ?? [], 'fiscal_regime')
        ?: data_get($company ?? [], 'regimen_fiscal')
        ?: '626'
    ));

    $__pdfIssuerRegimenLabelT2 = $__pdfFormatRegimenT2($__pdfIssuerRegimenCodeT2);

    // BEXIA_V5523T3_BRANCH_DASH_FALLBACK
    if (trim((string) $__pdfBranchNameT2) === '' || trim((string) $__pdfBranchNameT2) === '-') {
        $__pdfBranchNameT2 = $__pdfCompanyNameT2();
    }

@endphp



@php
    /*
     * BEXIA_V5523T1_PDF_BRANCH_AND_FISCAL_REGIME
     * PDF CFDI:
     * - Sucursal real si existe.
     * - Si no hay sucursal, usar empresa.
     * - Régimen fiscal como código - nombre.
     */

    $__pdfInvoice = $invoice ?? ($record ?? null);

    $__pdfRegimenFiscalMap = [
        '601' => 'General de Ley Personas Morales',
        '603' => 'Personas Morales con Fines no Lucrativos',
        '605' => 'Sueldos y Salarios e Ingresos Asimilados a Salarios',
        '606' => 'Arrendamiento',
        '607' => 'Régimen de Enajenación o Adquisición de Bienes',
        '608' => 'Demás ingresos',
        '610' => 'Residentes en el Extranjero sin Establecimiento Permanente en México',
        '611' => 'Ingresos por Dividendos (socios y accionistas)',
        '612' => 'Personas Físicas con Actividades Empresariales y Profesionales',
        '614' => 'Ingresos por intereses',
        '615' => 'Régimen de los ingresos por obtención de premios',
        '616' => 'Sin obligaciones fiscales',
        '620' => 'Sociedades Cooperativas de Producción que optan por diferir sus ingresos',
        '621' => 'Incorporación Fiscal',
        '622' => 'Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras',
        '623' => 'Opcional para Grupos de Sociedades',
        '624' => 'Coordinados',
        '625' => 'Régimen de las Actividades Empresariales con ingresos a través de Plataformas Tecnológicas',
        '626' => 'Régimen Simplificado de Confianza',
    ];

    $__pdfFormatRegimenFiscal = function ($code) use ($__pdfRegimenFiscalMap): string {
        $code = trim((string) $code);

        if ($code === '') {
            return '';
        }

        return $code.' - '.($__pdfRegimenFiscalMap[$code] ?? 'Régimen fiscal SAT');
    };

    $__pdfCompanyName = function () use ($__pdfInvoice): string {
        $companyId = (int) data_get($__pdfInvoice, 'company_id');

        if ($companyId > 0 && \Illuminate\Support\Facades\Schema::hasTable('companies')) {
            $company = \Illuminate\Support\Facades\DB::table('companies')->where('id', $companyId)->first();

            if ($company) {
                foreach ([
                    'commercial_name',
                    'display_name',
                    'name',
                    'business_name',
                    'legal_name',
                    'fiscal_name',
                ] as $field) {
                    $value = trim((string) ($company->{$field} ?? ''));

                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        foreach ([
            'issuer.name',
            'issuer.fiscal_name',
            'issuer.legal_name',
            'company.name',
            'company.commercial_name',
            'company.display_name',
        ] as $key) {
            $value = trim((string) data_get(get_defined_vars(), $key));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    };

    $__pdfRowName = function (string $table, int $id): string {
        if ($id <= 0 || ! \Illuminate\Support\Facades\Schema::hasTable($table)) {
            return '';
        }

        $row = \Illuminate\Support\Facades\DB::table($table)->where('id', $id)->first();

        if (! $row) {
            return '';
        }

        foreach ([
            'commercial_name',
            'display_name',
            'name',
            'branch_name',
            'sucursal_name',
            'store_name',
            'location_name',
            'warehouse_name',
            'description',
            'city',
        ] as $field) {
            $value = trim((string) ($row->{$field} ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    };

    $__pdfBranchFromRow = function (string $table, int $id, bool $allowOwnName = true) use (&$__pdfBranchFromRow, $__pdfRowName): string {
        if ($id <= 0 || ! \Illuminate\Support\Facades\Schema::hasTable($table)) {
            return '';
        }

        $row = \Illuminate\Support\Facades\DB::table($table)->where('id', $id)->first();

        if (! $row) {
            return '';
        }

        foreach ([
            'branch_id' => ['branches', 'company_branches', 'branch_offices'],
            'company_branch_id' => ['company_branches', 'branches', 'branch_offices'],
            'sucursal_id' => ['branches', 'company_branches', 'branch_offices'],
            'store_id' => ['stores', 'branches', 'company_branches'],
            'location_id' => ['locations', 'branches', 'company_branches'],
            'warehouse_id' => ['warehouses', 'branches', 'company_branches'],
        ] as $field => $tables) {
            $linkedId = (int) ($row->{$field} ?? 0);

            if ($linkedId <= 0) {
                continue;
            }

            foreach ($tables as $linkedTable) {
                $name = $__pdfRowName($linkedTable, $linkedId);

                if ($name !== '') {
                    return $name;
                }
            }
        }

        if (! $allowOwnName) {
            return '';
        }

        return $__pdfRowName($table, $id);
    };

    $__pdfBranchName = '';

    foreach ([
        'branch_name',
        'sucursal_name',
        'store_name',
        'location_name',
        'warehouse_name',
    ] as $field) {
        $value = trim((string) data_get($__pdfInvoice, $field));

        if ($value !== '') {
            $__pdfBranchName = $value;
            break;
        }
    }

    if ($__pdfBranchName === '') {
        $directLookupMap = [
            'branch_id' => ['branches', 'company_branches', 'branch_offices'],
            'company_branch_id' => ['company_branches', 'branches', 'branch_offices'],
            'sucursal_id' => ['branches', 'company_branches', 'branch_offices'],
            'store_id' => ['stores', 'branches', 'company_branches'],
            'location_id' => ['locations', 'branches', 'company_branches'],
            'warehouse_id' => ['warehouses', 'branches', 'company_branches'],
        ];

        foreach ($directLookupMap as $field => $tables) {
            $id = (int) data_get($__pdfInvoice, $field);

            if ($id <= 0) {
                continue;
            }

            foreach ($tables as $table) {
                $__pdfBranchName = $__pdfBranchFromRow($table, $id, true);

                if ($__pdfBranchName !== '') {
                    break 2;
                }
            }
        }
    }

    if ($__pdfBranchName === '') {
        $posLookupMap = [
            'point_of_sale_id' => ['points_of_sale', 'sale_points', 'pos_points', 'pos_terminals', 'cash_registers', 'pos_registers'],
            'sale_point_id' => ['sale_points', 'points_of_sale', 'pos_points', 'pos_terminals', 'cash_registers', 'pos_registers'],
            'pos_id' => ['pos_points', 'points_of_sale', 'sale_points', 'pos_terminals'],
            'pos_terminal_id' => ['pos_terminals', 'points_of_sale', 'sale_points'],
            'cash_register_id' => ['cash_registers', 'pos_registers', 'points_of_sale', 'sale_points'],
        ];

        foreach ($posLookupMap as $field => $tables) {
            $id = (int) data_get($__pdfInvoice, $field);

            if ($id <= 0) {
                continue;
            }

            foreach ($tables as $table) {
                $__pdfBranchName = $__pdfBranchFromRow($table, $id, false);

                if ($__pdfBranchName !== '') {
                    break 2;
                }
            }
        }
    }

    /*
     * Si viene de serie CFDI, solo usar la serie para llegar a sucursal/PDV.
     * No usar billing_series.name porque puede ser "Facturación Prueba DEV".
     */
    if ($__pdfBranchName === '') {
        $billingSeriesId = (int) data_get($__pdfInvoice, 'billing_series_id');

        if ($billingSeriesId > 0 && \Illuminate\Support\Facades\Schema::hasTable('billing_series')) {
            $series = \Illuminate\Support\Facades\DB::table('billing_series')->where('id', $billingSeriesId)->first();

            if ($series) {
                foreach ([
                    'branch_name',
                    'sucursal_name',
                    'store_name',
                    'location_name',
                    'warehouse_name',
                ] as $field) {
                    $value = trim((string) ($series->{$field} ?? ''));

                    if ($value !== '') {
                        $__pdfBranchName = $value;
                        break;
                    }
                }

                if ($__pdfBranchName === '') {
                    foreach ([
                        'branch_id' => ['branches', 'company_branches', 'branch_offices'],
                        'company_branch_id' => ['company_branches', 'branches', 'branch_offices'],
                        'sucursal_id' => ['branches', 'company_branches', 'branch_offices'],
                        'store_id' => ['stores', 'branches', 'company_branches'],
                        'warehouse_id' => ['warehouses', 'branches', 'company_branches'],
                        'point_of_sale_id' => ['points_of_sale', 'sale_points', 'pos_points', 'pos_terminals', 'cash_registers', 'pos_registers'],
                        'sale_point_id' => ['sale_points', 'points_of_sale', 'pos_points', 'pos_terminals', 'cash_registers', 'pos_registers'],
                        'pos_id' => ['pos_points', 'points_of_sale', 'sale_points', 'pos_terminals'],
                        'pos_terminal_id' => ['pos_terminals', 'points_of_sale', 'sale_points'],
                        'cash_register_id' => ['cash_registers', 'pos_registers', 'points_of_sale', 'sale_points'],
                    ] as $field => $tables) {
                        $id = (int) ($series->{$field} ?? 0);

                        if ($id <= 0) {
                            continue;
                        }

                        foreach ($tables as $table) {
                            $__pdfBranchName = $__pdfBranchFromRow($table, $id, str_contains($field, 'branch') || str_contains($field, 'sucursal') || str_contains($field, 'store') || str_contains($field, 'warehouse'));

                            if ($__pdfBranchName !== '') {
                                break 2;
                            }
                        }
                    }
                }
            }
        }
    }

    if ($__pdfBranchName === '' || mb_strtolower($__pdfBranchName) === 'matriz') {
        $__pdfBranchName = $__pdfCompanyName();
    }

    $__pdfIssuerRegimenCode = trim((string) (
        data_get($issuer ?? [], 'fiscal_regime')
        ?: data_get($issuer ?? [], 'regimen_fiscal')
        ?: data_get($company ?? [], 'fiscal_regime')
        ?: data_get($company ?? [], 'regimen_fiscal')
        ?: data_get($__pdfInvoice, 'issuer_fiscal_regime')
        ?: data_get($__pdfInvoice, 'company_fiscal_regime')
    ));

    $__pdfRegimenFiscalEmisor = $__pdfFormatRegimenFiscal($__pdfIssuerRegimenCode);
@endphp


@if (! $isStamped)
    <div class="preliminar">NO TIMBRADO</div>
@endif

<div class="brand-header clearfix">
    <div class="logo-col">
        @if ($logoDataUri)
            <img src="{{ $logoDataUri }}" alt="Logo">
        @else
            <div style="font-size:18px;font-weight:800;margin-top:20px;">BEXIA</div>
        @endif
    </div>

    <div class="company-col">
        <div class="company-title">{{ $company->business_name ?: ($company->name ?? '') }}</div>
        <div class="company-sub">
            R.F.C.: {{ $company->tax_id ?? '' }}
            &nbsp; • &nbsp;
            Régimen Fiscal: {{ $__pdfIssuerRegimenLabelT2 }}
        </div>
        <div class="company-address">{{ $companyAddress }}</div>
    </div>

    <div class="branch-col">
        <div class="branch-title">SUCURSAL</div>
        <div>{{ $__pdfBranchNameT2 }}</div>
    </div>
</div>

<div class="header-row clearfix">
    <div class="client-block">
        <div class="client-title">CLIENTE</div>
        <div class="client-name upper">{{ $invoice->customer_fiscal_name ?: ($invoice->customer_name ?? '') }}</div>
        <div class="upper address">{{ $customerAddress }}</div>
        <div class="kv-line">RFC: <span class="bold">{{ $invoice->customer_rfc ?? '' }}</span></div>
        <div class="kv-line">
            Régimen Fiscal (Receptor):
            <span class="bold">{{ $invoice->customer_tax_regime_code ?? '' }}</span>
        </div>
        <div class="kv-line">
            Dirección de entrega:
            <span class="bold">{{ $invoice->customer_fiscal_name ?: ($invoice->customer_name ?? '') }}</span>
        </div>
    </div>

    <div class="meta-block">
        <table class="meta-table">
            <tr>
                <td>Folio</td>
                <td>{{ trim(($invoice->cfdi_series ?? '').' '.str_pad((string) ($invoice->cfdi_folio ?? ''), 5, '0', STR_PAD_LEFT)) }}</td>
            </tr>
            <tr>
                <td>Tipo de comprobante</td>
                <td>{{ $cfdiTypeLabel }}</td>
            </tr>
            <tr>
                <td>Fecha de factura</td>
                <td>{{ optional($invoice->invoice_date ? \Carbon\Carbon::parse($invoice->invoice_date) : $generatedAt)->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td>Origen</td>
                <td>{{ $invoice->source_number ?? $invoice->source_type ?? '' }}</td>
            </tr>
            <tr>
                <td>Uso CFDI</td>
                <td>{{ $cfdiUseLabel }}</td>
            </tr>
            <tr>
                <td>Método / Forma</td>
                <td>{{ $paymentLabel }}</td>
            </tr>
            <tr>
                <td>Folio Fiscal UUID</td>
                <td>{{ $invoice->cfdi_uuid ?: ($xmlInfo['uuid'] ?? '') }}</td>
            </tr>
        </table>
    </div>
</div>

<table class="lines">
    <thead>
        <tr>
            <th style="width:7%;">Cantidad</th>
            <th style="width:9%;">Unidad de<br>Medida</th>
            <th style="width:10%;">Clave SAT</th>
            <th style="width:12%;">Referencia interna</th>
            <th style="width:12%;">Código de barras</th>
            <th>Descripción</th>
            <th style="width:10%;">Precio Unitario</th>
            <th style="width:12%;">Impuestos</th>
            <th style="width:10%;">Importe</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($lines as $line)
            <tr>
                <td>{{ rtrim(rtrim(number_format((float) $line['quantity'], 4, '.', ''), '0'), '.') }}</td>
                <td>
                    {{ $line['unit'] }}
                    @if ($line['unit_name'] && $line['unit_name'] !== $line['unit'])
                        - {{ $line['unit_name'] }}
                    @endif
                </td>
                <td>{{ $line['sat_code'] }}</td>
                <td>{{ $line['internal_ref'] }}</td>
                <td>{{ $line['barcode'] }}</td>
                <td class="desc">{{ $line['description'] }}</td>
                <td class="right">$ {{ number_format((float) $line['unit_price'], 2) }}</td>
                <td class="right">$ {{ number_format((float) $line['tax'], 2) }}</td>
                <td class="right">$ {{ number_format((float) $line['subtotal'], 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="totals-wrap">
    <table class="totals">
        <tr>
            <td>Subtotal</td>
            <td>$ {{ number_format((float) $subtotal, 2) }}</td>
        </tr>
        <tr>
            <td>Impuestos</td>
            <td>$ {{ number_format((float) $taxTotal, 2) }}</td>
        </tr>
        <tr>
            <td>Total</td>
            <td>$ {{ number_format((float) $total, 2) }}</td>
        </tr>
    </table>
</div>

<div class="amount-words">
    <div class="words">{{ $amountWords }}</div>
    <div class="legend">Este documento es una representación impresa de un CFDI.</div>
</div>

@php
    // BEXIA_V5523Q7B_SOFT_WRAP_FISCAL_TEXT
    // Solo agrega espacios visuales para que Dompdf pueda envolver sellos largos.
    // No modifica XML, sello, UUID ni timbrado.
    $bexiaSoftWrapFiscalText = function ($value, int $chunk = 150): string {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        return trim(chunk_split($value, $chunk, ' '));
    };
@endphp

<div class="fiscal-wrap clearfix">
    <div class="qr-col">
        @if ($qrDataUri)
            <img src="{{ $qrDataUri }}" alt="QR CFDI">
        @else
            <div class="qr-placeholder">
                @if ($isStamped)
                    QR CFDI pendiente
                @else
                    QR disponible al timbrar
                @endif
            </div>
        @endif
    </div>

    <div class="fiscal-col">
        <div class="band">Sello digital del emisor</div>
        <div class="seal mono">{{ $bexiaSoftWrapFiscalText($xmlInfo['sello_cfdi'] ?? '', 150) }}</div>

        <div class="band">Sello digital del SAT</div>
        <div class="seal mono">{{ $bexiaSoftWrapFiscalText($xmlInfo['sello_sat'] ?? '', 150) }}</div>

        <div class="band">Cadena original del complemento del certificado digital del SAT</div>
        <div class="seal mono">{{ $bexiaSoftWrapFiscalText($xmlInfo['cadena_sat'] ?? '', 165) }}</div>

        <div class="band">Información Extra</div>
        <div class="info-extra mono">
            Certificado del emisor: {{ $xmlInfo['issuer_certificate'] ?? '' }}
            | Certificado SAT: {{ $xmlInfo['sat_certificate'] ?? '' }}
            | Lugar de expedición: {{ $xmlInfo['expedition_place'] ?? ($company->fiscal_postal_code ?? $company->postal_code ?? '') }}
            | Régimen Fiscal: {{ $__pdfIssuerRegimenLabelT2 ?: ($xmlInfo['issuer_regime'] ?? ($company->tax_regime ?? '')) }}
            | Fecha de Emisión: {{ $xmlInfo['emission_date'] ?? '' }}
            | Fecha de Certificación: {{ $xmlInfo['stamp_date'] ?? '' }}
            | Folio fiscal (UUID): {{ $invoice->cfdi_uuid ?: ($xmlInfo['uuid'] ?? '') }}
        </div>
    </div>
</div>
</body>
</html>
