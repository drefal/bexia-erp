@php
    $notice = null;

    if (! empty($documentType) && ! empty($documentId) && class_exists(\App\Support\PurchaseVisualNotices::class)) {
        $notice = \App\Support\PurchaseVisualNotices::notice(
            (string) $documentType,
            (int) $documentId
        );
    }

    $type = $notice['type'] ?? null;

    $style = match ($type) {
        'success' => 'background:#ecfdf5;border-color:#bbf7d0;color:#166534;',
        'warning' => 'background:#fffbeb;border-color:#fde68a;color:#92400e;',
        'danger' => 'background:#fef2f2;border-color:#fecaca;color:#991b1b;',
        'info' => 'background:#eff6ff;border-color:#bfdbfe;color:#1e40af;',
        'gray' => 'background:#f8fafc;border-color:#e2e8f0;color:#475569;',
        default => '',
    };

    $dotStyle = match ($type) {
        'success' => 'background:#22c55e;',
        'warning' => 'background:#f59e0b;',
        'danger' => 'background:#ef4444;',
        'info' => 'background:#3b82f6;',
        'gray' => 'background:#94a3b8;',
        default => 'background:#94a3b8;',
    };
@endphp

@if($notice)
    <div style="width:100%;border:1px solid;border-radius:14px;padding:10px 13px;margin-bottom:8px;{{ $style }}">
        <div style="display:flex;align-items:center;gap:8px;">
            <span style="width:9px;height:9px;border-radius:999px;display:inline-flex;{{ $dotStyle }}"></span>

            <div style="font-size:13px;font-weight:700;line-height:1.25;">
                {{ $notice['title'] }}
            </div>
        </div>

        @if(! empty($notice['body']))
            <div style="font-size:12px;margin-top:4px;line-height:1.35;">
                {{ $notice['body'] }}
            </div>
        @endif

        @if(! empty($notice['meta']))
            <div style="font-size:11px;margin-top:4px;opacity:.8;line-height:1.3;">
                {{ $notice['meta'] }}
            </div>
        @endif
    </div>
@endif
