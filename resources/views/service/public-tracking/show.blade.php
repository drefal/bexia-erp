<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Seguimiento de reparación - {{ $rows['Folio'] ?? 'Servicio' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f4f6;
            color: #111827;
        }
        .wrap {
            width: min(960px, calc(100% - 28px));
            margin: 24px auto;
        }
        .card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, .08);
            overflow: hidden;
        }
        .header {
            padding: 22px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .logo {
            max-width: 150px;
            max-height: 64px;
            object-fit: contain;
        }
        .brand {
            font-size: 13px;
            color: #6b7280;
        }
        .title {
            font-size: 24px;
            margin: 4px 0 0;
            font-weight: 800;
        }
        .body {
            padding: 22px;
        }
        .status {
            border-radius: 16px;
            padding: 18px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            margin-bottom: 18px;
        }
        .status-label {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 6px;
        }
        .status-message {
            color: #4b5563;
            line-height: 1.45;
        }
        .grid {
            display: grid;
            grid-template-columns: 180px 1fr;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            overflow: hidden;
        }
        .label, .value {
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
            min-height: 38px;
            font-size: 14px;
        }
        .label {
            background: #f9fafb;
            font-weight: 700;
            color: #374151;
            border-right: 1px solid #e5e7eb;
        }
        .value {
            white-space: pre-wrap;
        }
        .timeline {
            display: grid;
            gap: 8px;
            margin-top: 16px;
        }
        .step {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 10px 12px;
            background: #fff;
        }
        .dot {
            width: 16px;
            height: 16px;
            border-radius: 999px;
            background: #d1d5db;
            margin-top: 2px;
            flex: 0 0 16px;
        }
        .step.done .dot {
            background: #111827;
        }
        .step-title {
            font-weight: 700;
        }
        .step-date {
            color: #6b7280;
            font-size: 12px;
            margin-top: 2px;
        }
        .section {
            margin-top: 22px;
        }
        .section h2 {
            margin: 0 0 10px;
            font-size: 17px;
        }
        .notice {
            padding: 12px;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            color: #374151;
            font-size: 13px;
            line-height: 1.45;
        }
        .footer {
            padding: 16px 22px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 12px;
            background: #f9fafb;
        }
        @media (max-width: 680px) {
            .header { align-items: flex-start; flex-direction: column; }
            .grid { grid-template-columns: 1fr; }
            .label { border-right: 0; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <header class="header">
                <div>
                    <div class="brand">{{ $company?->name ?? $company?->business_name ?? config('app.name') }}</div>
                    <h1 class="title">Seguimiento de reparación</h1>
                </div>

                @if($companyLogoUrl)
                    <img src="{{ $companyLogoUrl }}" class="logo" alt="Logo">
                @endif
            </header>

            <main class="body">
                <section class="status">
                    <div class="status-label">{{ $customerStatus['label'] }}</div>
                    <div class="status-message">{{ $customerStatus['message'] }}</div>
                </section>

                <section class="section">
                    <h2>Datos del servicio</h2>
                    <div class="grid">
                        @foreach($rows as $label => $value)
                            <div class="label">{{ $label }}</div>
                            <div class="value">{{ filled($value) ? $value : '—' }}</div>
                        @endforeach
                    </div>
                </section>

                <section class="section">
                    <h2>Avance</h2>
                    <div class="timeline">
                        @foreach($timeline as $step)
                            <div class="step {{ $step['done'] ? 'done' : '' }}">
                                <div class="dot"></div>
                                <div>
                                    <div class="step-title">{{ $step['label'] }}</div>
                                    <div class="step-date">{{ filled($step['date']) ? $step['date'] : 'Pendiente' }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                @if(filled($publicNotes['reported_problem'] ?? '') || filled($publicNotes['received_condition'] ?? '') || filled($publicNotes['resolution'] ?? ''))
                    <section class="section">
                        <h2>Información visible para cliente</h2>

                        @if(filled($publicNotes['reported_problem'] ?? ''))
                            <div class="notice" style="margin-bottom: 8px;">
                                <strong>Problema reportado:</strong><br>
                                {{ $publicNotes['reported_problem'] }}
                            </div>
                        @endif

                        @if(filled($publicNotes['received_condition'] ?? ''))
                            <div class="notice" style="margin-bottom: 8px;">
                                <strong>Condición de recepción:</strong><br>
                                {{ $publicNotes['received_condition'] }}
                            </div>
                        @endif

                        @if(filled($publicNotes['resolution'] ?? ''))
                            <div class="notice">
                                <strong>Solución / trabajo realizado:</strong><br>
                                {{ $publicNotes['resolution'] }}
                            </div>
                        @endif
                    </section>
                @endif

                <section class="section">
                    <div class="notice">
                        Esta página muestra información de seguimiento. Para aclaraciones, cambios o entrega física del equipo, comunícate directamente con la empresa.
                    </div>
                </section>
            </main>

            <footer class="footer">
                Última actualización: {{ filled($updatedAt) ? $updatedAt : '—' }} · Bexia ERP
            </footer>
        </div>
    </div>
</body>
</html>
