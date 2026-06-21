<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $document['title'] }} - {{ $rows['Folio'] ?? 'Servicio' }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            color: #111827;
            margin: 0;
            background: #f3f4f6;
        }
        .page {
            width: 216mm;
            min-height: 279mm;
            margin: 16px auto;
            background: #fff;
            padding: 18mm;
            border: 1px solid #e5e7eb;
        }
        .toolbar {
            width: 216mm;
            margin: 16px auto 0;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }
        .btn {
            border: 1px solid #111827;
            background: #111827;
            color: #fff;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
        }
        .header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 24px;
            border-bottom: 2px solid #111827;
            padding-bottom: 14px;
            margin-bottom: 18px;
        }
        .logo {
            max-width: 150px;
            max-height: 70px;
            object-fit: contain;
        }
        h1 {
            font-size: 22px;
            margin: 0 0 4px;
        }
        .subtitle {
            color: #4b5563;
            font-size: 13px;
        }
        .meta {
            text-align: right;
            font-size: 12px;
            color: #374151;
            line-height: 1.5;
        }
        .section {
            margin-top: 18px;
        }
        .section h2 {
            font-size: 15px;
            margin: 0 0 8px;
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 5px;
        }
        .grid {
            display: grid;
            grid-template-columns: 42mm 1fr;
            border: 1px solid #e5e7eb;
        }
        .label, .value {
            padding: 7px 9px;
            border-bottom: 1px solid #e5e7eb;
            min-height: 30px;
            font-size: 12px;
        }
        .label {
            background: #f9fafb;
            font-weight: 700;
            border-right: 1px solid #e5e7eb;
        }
        .value {
            white-space: pre-wrap;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        th, td {
            border: 1px solid #e5e7eb;
            padding: 7px 9px;
            vertical-align: top;
        }
        th {
            background: #f9fafb;
            text-align: left;
        }
        .totals {
            width: 80mm;
            margin-left: auto;
        }
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 42px;
        }
        .signature {
            text-align: center;
            font-size: 12px;
            padding-top: 34px;
            border-top: 1px solid #111827;
        }
        .small {
            color: #6b7280;
            font-size: 11px;
        }
        .evidence-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 10px;
        }
        .evidence-pill {
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            border-radius: 6px;
            padding: 8px;
            font-size: 11px;
        }
        .evidence-gallery {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 10px;
        }
        .evidence-card {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 7px;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .evidence-card img {
            width: 100%;
            height: 42mm;
            object-fit: contain;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            display: block;
            margin-bottom: 6px;
        }
        .evidence-name {
            font-weight: 700;
            font-size: 11px;
            overflow-wrap: anywhere;
        }
        .evidence-meta {
            color: #6b7280;
            font-size: 10px;
            line-height: 1.35;
            margin-top: 3px;
        }
        .evidence-table td {
            overflow-wrap: anywhere;
        }
        .notice {
            border: 1px solid #d1d5db;
            background: #f9fafb;
            padding: 10px;
            font-size: 11px;
            line-height: 1.45;
        }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page {
                width: auto;
                min-height: auto;
                margin: 0;
                border: 0;
                padding: 12mm;
            }
            a { color: #111827; text-decoration: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn" onclick="window.print()">Imprimir / Guardar PDF</button>
    </div>

    <main class="page">
        <header class="header">
            <div>
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" class="logo" alt="Logo">
                @endif
                <h1>{{ $document['title'] }}</h1>
                <div class="subtitle">{{ $document['subtitle'] }}</div>
            </div>

            <div class="meta">
                <strong>{{ $company?->name ?? $company?->business_name ?? config('app.name') }}</strong><br>
                Folio: <strong>{{ $rows['Folio'] ?? '' }}</strong><br>
                Impreso: {{ $printedAt->format('d/m/Y H:i') }}<br>
                @if($document['customer_copy'])
                    Copia cliente / archivo
                @else
                    Documento interno
                @endif
            </div>
        </header>

        <section class="section">
            <h2>Datos principales</h2>
            <div class="grid">
                @foreach($rows as $label => $value)
                    <div class="label">{{ $label }}</div>
                    <div class="value">{{ filled($value) ? $value : '—' }}</div>
                @endforeach
            </div>
        </section>

        @if(in_array($type, ['quote', 'internal'], true))
            <section class="section">
                <h2>Refacciones / materiales / conceptos</h2>
                @if(count($parts))
                    <table>
                        <thead>
                            <tr>
                                <th>Concepto</th>
                                <th style="width: 24mm;">Cantidad</th>
                                <th style="width: 30mm;">Precio</th>
                                <th style="width: 30mm;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($parts as $part)
                                <tr>
                                    <td>{{ $part['concept'] }}</td>
                                    <td>{{ $part['quantity'] }}</td>
                                    <td>{{ $part['unit_price'] }}</td>
                                    <td>{{ $part['total'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="notice">Sin refacciones/materiales capturados.</div>
                @endif
            </section>

            <section class="section">
                <h2>Totales</h2>
                <table class="totals">
                    <tr>
                        <th>Refacciones/materiales</th>
                        <td>{{ $totals['refacciones_materiales'] }}</td>
                    </tr>
                    <tr>
                        <th>Horas estimadas</th>
                        <td>{{ $totals['horas_estimadas'] ?: '—' }}</td>
                    </tr>
                    <tr>
                        <th>Mano de obra estimada</th>
                        <td>{{ $totals['mano_obra_estimada'] }}</td>
                    </tr>
                    <tr>
                        <th>Total presupuesto</th>
                        <td><strong>{{ $totals['presupuesto_total'] }}</strong></td>
                    </tr>
                    @if($type === 'internal')
                        <tr>
                            <th>Horas reales</th>
                            <td>{{ $totals['horas_reales'] ?: '—' }}</td>
                        </tr>
                        <tr>
                            <th>Costo real mano de obra</th>
                            <td>{{ $totals['costo_real_mano_obra'] }}</td>
                        </tr>
                    @endif
                </table>
            </section>
        @endif

        @if(count($attachments))
            @php
                $evidenceImages = collect($attachments)
                    ->filter(fn ($attachment) => (bool) ($attachment['is_image'] ?? false))
                    ->values();

                $visibleImages = $evidenceImages->take(12);
                $hiddenImageCount = max($evidenceImages->count() - $visibleImages->count(), 0);

                $evidenceOther = collect($attachments)
                    ->reject(fn ($attachment) => (bool) ($attachment['is_image'] ?? false))
                    ->values();
            @endphp

            <section class="section">
                <h2>Evidencias adjuntas</h2>

                <div class="evidence-summary">
                    <div class="evidence-pill">
                        <strong>{{ $evidenceImages->count() }}</strong><br>
                        Imágenes adjuntas
                    </div>
                    <div class="evidence-pill">
                        <strong>{{ $evidenceOther->count() }}</strong><br>
                        Documentos / videos / archivos
                    </div>
                    <div class="evidence-pill">
                        <strong>{{ count($attachments) }}</strong><br>
                        Evidencias totales
                    </div>
                </div>

                @if($visibleImages->count())
                    <div class="evidence-gallery">
                        @foreach($visibleImages as $attachment)
                            <div class="evidence-card">
                                @if(($attachment['url'] ?? '#') !== '#')
                                    <img src="{{ $attachment['url'] }}" alt="{{ $attachment['name'] }}">
                                @else
                                    <div class="notice">Imagen adjunta sin ruta pública disponible.</div>
                                @endif

                                <div class="evidence-name">{{ $attachment['type_label'] ?? 'Evidencia fotográfica' }}</div>
                                <div class="evidence-meta">
                                    Evidencia adjunta al expediente<br>
                                    Etapa: {{ $attachment['stage'] ?: '—' }}<br>
                                    Fecha: {{ $attachment['created_at'] ?: '—' }}
                                    @if(!empty($attachment['notes']))
                                        <br>Notas: {{ $attachment['notes'] }}
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if($hiddenImageCount > 0)
                        <div class="notice" style="margin-top: 10px;">
                            Existen {{ $hiddenImageCount }} imagen(es) adicional(es) adjuntas.
                            No se muestran para mantener la impresión legible.
                        </div>
                    @endif
                @endif

                @if($evidenceOther->count())
                    <div style="margin-top: 12px;">
                        <table class="evidence-table">
                            <thead>
                                <tr>
                                    <th style="width: 34mm;">Tipo</th>
                                    <th>Archivo</th>
                                    <th style="width: 35mm;">Etapa / fecha</th>
                                    <th>Notas</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($evidenceOther as $attachment)
                                    <tr>
                                        <td>
                                            <strong>{{ $attachment['type_label'] ?? 'Archivo adjunto' }}</strong><br>
                                            <span class="small">Existe como evidencia adjunta.</span>
                                        </td>
                                        <td>
                                            Evidencia adjunta al expediente
                                            @if(!empty($attachment['extension']))
                                                <br><span class="small">Tipo de archivo: .{{ $attachment['extension'] }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $attachment['stage'] ?: '—' }}<br>
                                            <span class="small">{{ $attachment['created_at'] ?: '—' }}</span>
                                        </td>
                                        <td>{{ $attachment['notes'] ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if(!$visibleImages->count() && !$evidenceOther->count())
                    <div class="notice">Sin evidencias adjuntas para esta reparación.</div>
                @endif
            </section>
        @endif

        @if($type === 'reception')
            <section class="section">
                <h2>Condiciones de recepción</h2>
                <div class="notice">
                    El cliente entrega el producto/equipo descrito para diagnóstico o reparación.
                    La recepción no implica autorización automática de reparación ni aceptación de presupuesto.
                    La empresa notificará el presupuesto o resolución correspondiente según aplique.
                </div>
            </section>
        @endif

        @if($type === 'quote')
            <section class="section">
                <h2>Autorización de presupuesto</h2>
                <div class="notice">
                    La autorización del presupuesto permite iniciar o continuar el proceso de reparación.
                    Los importes pueden variar si durante la reparación se detectan daños adicionales o refacciones no consideradas.
                </div>
            </section>
        @endif

        @if($type === 'delivery')
            <section class="section">
                <h2>Entrega</h2>
                <div class="notice">
                    El cliente o persona autorizada recibe el producto/equipo descrito y manifiesta haber revisado su entrega física.
                </div>
            </section>
        @endif

        <section class="signatures">
            <div class="signature">
                Cliente / recibe<br>
                <span class="small">Nombre y firma</span>
            </div>
            <div class="signature">
                Empresa / responsable<br>
                <span class="small">Nombre y firma</span>
            </div>
        </section>
    </main>
</body>
</html>
