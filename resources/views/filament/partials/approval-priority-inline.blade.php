@php
    $approvalRow = $row ?? null;

    if (is_array($approvalRow)) {
        $approvalRow = (object) $approvalRow;
    }

    $documentType = (string) ($approvalRow->document_type ?? '');
    $isSalesApproval = in_array($documentType, [
        'sales_quote',
        'sales_:quote',
        'sale_quote',
        'sales_order',
        'sales_margin_approval',
    ], true);

    $priorityMessage = $isSalesApproval && $approvalRow
        ? \App\Support\SalesApprovalWorkflow::approvalPriorityMessage($approvalRow)
        : null;
@endphp

@if($isSalesApproval && $approvalRow)
    <div style="margin-top:6px;display:flex;align-items:center;gap:7px;flex-wrap:wrap;padding:5px 8px;border-radius:10px;background:#fff7ed;border:1px solid #fed7aa;width:max-content;max-width:100%;">
        {!! \App\Support\SalesApprovalWorkflow::approvalPriorityBadgeHtml($approvalRow) !!}

        @if($priorityMessage)
            <span style="font-size:11px;color:#7c2d12;line-height:1.25;">
                {{ $priorityMessage }}
            </span>
        @endif
    </div>
@endif
