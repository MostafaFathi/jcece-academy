<!DOCTYPE html>
<html lang="{{ $document->locale }}" dir="{{ $document->locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <style>
        body { color: #333333; font-family: dejavusans, sans-serif; font-size: 10pt; }
        .header { border-bottom: 2mm solid #6B1D32; padding-bottom: 5mm; }
        .brand { color: #6B1D32; font-size: 17pt; font-weight: bold; }
        .tagline { color: #777777; font-size: 7pt; letter-spacing: 1px; }
        .logo { width: 21mm; vertical-align: middle; }
        h1 { color: #6B1D32; font-size: 18pt; margin: 9mm 0 2mm; }
        .subheading { color: #777777; font-size: 9pt; margin-bottom: 7mm; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 7mm; }
        .meta td { padding: 2mm 3mm; border-bottom: 0.3mm solid #E6E6E6; vertical-align: top; }
        .label { color: #777777; font-size: 7pt; display: block; }
        .value { font-weight: bold; }
        .reference { font-size: 9pt; white-space: nowrap; }
        .lines { width: 100%; border-collapse: collapse; margin: 5mm 0; }
        .lines th { background: #6B1D32; color: #FFFFFF; padding: 3mm 2mm; text-align: {{ $document->locale === 'ar' ? 'right' : 'left' }}; }
        .lines td { padding: 3mm 2mm; border-bottom: 0.3mm solid #E6E6E6; vertical-align: top; }
        .lines .number { white-space: nowrap; text-align: left; direction: ltr; }
        .totals { width: 70mm; margin-{{ $document->locale === 'ar' ? 'right' : 'left' }}: auto; border-collapse: collapse; }
        .totals td { padding: 2mm 1mm; border-bottom: 0.3mm solid #E6E6E6; }
        .totals .number { text-align: left; direction: ltr; white-space: nowrap; }
        .final td { color: #6B1D32; font-weight: bold; font-size: 12pt; border-top: 1mm solid #FFD21E; }
        .note { margin-top: 9mm; color: #666666; font-size: 8pt; line-height: 1.6; }
        .footer { margin-top: 14mm; padding-top: 3mm; border-top: 0.4mm solid #E6E6E6; color: #777777; font-size: 7pt; }
    </style>
</head>
<body>
@php
    $arabic = $document->locale === 'ar';
    $currency = $document->currency;
    $methodLabels = [
        'bank_transfer' => ['تحويل بنكي', 'Bank transfer'],
        'wallet' => ['محفظة إلكترونية', 'Wallet'],
        'manual' => ['دفع يدوي', 'Manual payment'],
        'recorded_payment' => ['دفع مسجل', 'Recorded payment'],
        'complimentary' => ['طلب مجاني مكتمل', 'Completed complimentary order'],
    ];
    $methodLabel = ($methodLabels[$snapshot['payment_method'] ?? 'recorded_payment'] ?? $methodLabels['recorded_payment'])[$arabic ? 0 : 1];
@endphp
<div class="header">
    @if (is_file($logoPath))<img class="logo" src="{{ $logoPath }}" alt="JCEC Academy">@endif
    <span class="brand">JCEC Academy</span><br>
    <span class="tagline">LEARN · BUILD · LEAD</span>
</div>

<h1>{{ $document->kind === 'purchase' ? ($arabic ? 'إيصال طلب' : 'Order Receipt') : ($arabic ? 'إيصال استرداد' : 'Refund Receipt') }}</h1>
<div class="subheading">{{ $arabic ? 'مستند تجاري يعكس السجل المالي للطلب، وليس فاتورة ضريبية.' : 'Commercial transaction record, not a tax invoice.' }}</div>

<table class="meta">
    <tr>
        <td colspan="2"><span class="label">{{ $arabic ? 'رقم المستند' : 'Document number' }}</span><br><span class="value reference" dir="ltr">{{ $document->document_number }}</span></td>
    </tr>
    <tr>
        <td width="50%"><span class="label">{{ $arabic ? 'رقم الطلب' : 'Order reference' }}</span><br><span class="value" dir="ltr">{{ $snapshot['order_number'] }}</span></td>
        <td width="50%"><span class="label">{{ $arabic ? 'تاريخ الإصدار' : 'Issue date' }}</span><br><span class="value" dir="ltr">{{ $document->issued_at->format('Y-m-d H:i') }} UTC</span></td>
    </tr>
    <tr><td colspan="2"><span class="label">{{ $arabic ? 'العميل' : 'Customer' }}</span><br><span class="value">{{ $document->customer_name }}</span> · <span dir="ltr">{{ $document->customer_email }}</span></td></tr>
    @if ($document->kind === 'refund')
        <tr>
            <td colspan="2"><span class="label">{{ $arabic ? 'إيصال الشراء الأصلي' : 'Original purchase receipt' }}</span><br><span class="value reference" dir="ltr">{{ $snapshot['purchase_document_number'] ?? ($arabic ? 'غير متوفر' : 'Unavailable') }}</span></td>
        </tr>
        <tr><td colspan="2"><span class="label">{{ $arabic ? 'مرجع الاسترداد' : 'Refund reference' }}</span><br><span class="value" dir="ltr">#{{ $snapshot['refund_reference'] }}</span></td></tr>
    @endif
</table>

@if ($document->kind === 'purchase')
    <table class="lines">
        <thead><tr><th>{{ $arabic ? 'البند' : 'Item' }}</th><th>{{ $arabic ? 'الكمية' : 'Qty' }}</th><th>{{ $arabic ? 'السعر الأساسي' : 'Base price' }}</th><th>{{ $arabic ? 'الخصم' : 'Discount' }}</th><th>{{ $arabic ? 'الصافي' : 'Net' }}</th></tr></thead>
        <tbody>
        @foreach ($snapshot['items'] as $item)
            <tr><td>{{ $item['title'] }}
                @if (($item['promotional_discount'] ?? '0.00') !== '0.00')<br><small>{{ $arabic ? 'خصم ترويجي' : 'Promotion' }}: {{ $item['promotional_discount'] }} {{ $currency }}</small>@endif
                @if (($item['coupon_discount'] ?? '0.00') !== '0.00')<br><small>{{ $arabic ? 'خصم قسيمة' : 'Coupon' }}: {{ $item['coupon_discount'] }} {{ $currency }}</small>@endif
            </td><td class="number">{{ $item['quantity'] }}</td><td class="number">{{ $item['unit_price'] }} {{ $currency }}</td><td class="number">{{ $item['discount'] }} {{ $currency }}</td><td class="number">{{ $item['total'] }} {{ $currency }}</td></tr>
        @endforeach
        </tbody>
    </table>
    <table class="totals">
        <tr><td>{{ $arabic ? 'المجموع قبل الخصم' : 'Subtotal' }}</td><td class="number">{{ $snapshot['subtotal'] }} {{ $currency }}</td></tr>
        <tr><td>{{ $arabic ? 'الخصومات' : 'Discounts' }}</td><td class="number">{{ $snapshot['discount_total'] }} {{ $currency }}</td></tr>
        @if (($snapshot['tax_total'] ?? '0.00') !== '0.00')<tr><td>{{ $arabic ? 'ضريبة مسجلة في الطلب' : 'Tax recorded on order' }}</td><td class="number">{{ $snapshot['tax_total'] }} {{ $currency }}</td></tr>@endif
        <tr class="final"><td>{{ $arabic ? 'إجمالي الطلب' : 'Order total' }}</td><td class="number">{{ $snapshot['total'] }} {{ $currency }}</td></tr>
    </table>
    <div class="note">
        {{ $arabic ? 'طريقة التسوية' : 'Settlement method' }}: {{ $methodLabel }}.
        @if ($snapshot['coupon_code']) {{ $arabic ? 'القسيمة' : 'Coupon' }}: {{ $snapshot['coupon_code'] }}. @endif
    </div>
@else
    <table class="lines"><thead><tr><th>{{ $arabic ? 'التعديل المالي' : 'Financial adjustment' }}</th><th>{{ $arabic ? 'المبلغ' : 'Amount' }}</th></tr></thead><tbody>
        @if ($snapshot['selected_items'] !== [])
            @foreach ($snapshot['selected_items'] as $item)<tr><td>{{ $item['title'] }}</td><td class="number">{{ $item['amount'] }} {{ $currency }}</td></tr>@endforeach
        @else
            <tr><td>{{ $arabic ? 'استرداد يدوي مسجل للطلب' : 'Recorded manual order refund' }}</td><td class="number">{{ $snapshot['refund_amount'] }} {{ $currency }}</td></tr>
        @endif
    </tbody></table>
    <table class="totals"><tr class="final"><td>{{ $arabic ? 'المبلغ المسترد' : 'Refund amount' }}</td><td class="number">{{ $snapshot['refund_amount'] }} {{ $currency }}</td></tr><tr><td>{{ $arabic ? 'الصافي المتبقي بعد الاستردادات المكتملة' : 'Remaining net after completed refunds' }}</td><td class="number">{{ $snapshot['remaining_net'] }} {{ $currency }}</td></tr></table>
    <div class="note">{{ $arabic ? 'سُجل هذا الاسترداد بعد تأكيد معالجته يدويًا بتاريخ' : 'This refund was recorded after manual processing was confirmed on' }} <span dir="ltr">{{ $snapshot['completed_at'] }}</span>.</div>
@endif

<div class="footer">JCEC Academy · {{ $arabic ? 'أكاديمية الجزيرة للتدريب المهني' : 'Al Jazeera Academy for Professional Training' }}</div>
</body>
</html>
