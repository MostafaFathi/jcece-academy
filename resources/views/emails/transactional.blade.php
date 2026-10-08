<!DOCTYPE html>
<html lang="{{ $delivery->locale }}" dir="{{ $delivery->locale === 'ar' ? 'rtl' : 'ltr' }}">
<head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;color:#333333;background:#f6f6f6;margin:0;padding:24px}.card{background:#ffffff;max-width:620px;margin:auto;padding:32px;border-top:5px solid #6B1D32}.brand{font-size:22px;font-weight:bold;color:#6B1D32}.accent{width:72px;border-top:3px solid #FFD21E;margin:18px 0}a{color:#6B1D32;font-weight:bold}p{line-height:1.8}</style></head>
<body><div class="card"><div class="brand">JCEC Academy</div><div class="accent"></div>
@php
    $ar = $delivery->locale === 'ar';
    $details = $delivery->payload;
    $orderUrl = $delivery->order_id !== null ? url('/student/orders/'.$delivery->order_id) : null;
    $certificateUrl = $delivery->message_type === 'certificate_issued' ? url('/student/certificates') : null;
@endphp
<p>{{ $ar ? 'مرحبًا' : 'Hello' }} {{ $details['name'] ?? '' }}،</p>
@switch($delivery->message_type)
    @case('welcome')<p>{{ $ar ? 'تم إنشاء حسابك في أكاديمية الجزيرة بنجاح.' : 'Your JCEC Academy account has been created.' }}</p>@break
    @case('order_created')<p>{{ $ar ? 'تم إنشاء طلبك. اتبع تعليمات الدفع اليدوي في صفحة الطلب، ولن يتاح المحتوى حتى مراجعة الدفع.' : 'Your order has been created. Follow the manual payment instructions on the order page. Learning access starts after payment review.' }}</p>@break
    @case('purchase_completed')<p>{{ $ar ? 'اكتمل طلبك وأصبح المحتوى الذي اشتريته متاحًا. راجع صفحة الطلب للاطلاع على حالة الإيصال.' : 'Your order is complete and purchased learning access is ready. Check the order page for receipt status.' }}</p>@break
    @case('payment_rejected')<p>{{ $ar ? 'لم تتم الموافقة على إثبات الدفع لهذا الطلب. راجع صفحة الطلب لتقديم إثبات جديد أو التواصل مع الدعم.' : 'The payment proof for this order was not accepted. Review the order page to submit a new proof or contact support.' }}</p>@break
    @case('order_cancelled')<p>{{ $ar ? 'تم إلغاء طلبك قبل إتمام الدفع.' : 'Your order was cancelled before payment completion.' }}</p>@break
    @case('refund_completed')<p>{{ $ar ? 'تم تسجيل اكتمال الاسترداد لهذا الطلب. راجع صفحة الطلب للاطلاع على حالة مستند الاسترداد.' : 'The refund for this order has been recorded as complete. Check the order page for the refund receipt status.' }}</p>@break
    @case('refund_rejected')<p>{{ $ar ? 'لم تتم الموافقة على طلب الاسترداد لهذا الطلب. يمكنك مراجعة حالة الطلب أو التواصل مع الدعم.' : 'The refund request for this order was not approved. Review your order or contact support.' }}</p>@break
    @case('certificate_issued')<p>{{ $ar ? 'صدرت شهادتك وأصبحت متاحة في حسابك.' : 'Your certificate has been issued and is available in your account.' }}</p>@break
    @case('certificate_approval_granted')<p>{{ $ar ? 'تمت الموافقة على طلب شهادتك. ادخل إلى حسابك لمتابعة إصدار الشهادة.' : 'Your certificate request was approved. Sign in to continue certificate issuance.' }} {{ $details['course_title'] ?? '' }}</p>@break
@endswitch
@if ($orderUrl)<p>{{ $ar ? 'رقم الطلب' : 'Order reference' }}: <bdi>{{ $details['order_number'] ?? '' }}</bdi><br>{{ $ar ? 'المبلغ' : 'Amount' }}: <bdi>{{ $details['amount'] ?? '' }} {{ $details['currency'] ?? '' }}</bdi></p><p><a href="{{ $orderUrl }}">{{ $ar ? 'عرض الطلب بأمان' : 'View your order securely' }}</a></p>@endif
@if ($certificateUrl)<p><a href="{{ $certificateUrl }}">{{ $ar ? 'عرض شهاداتي' : 'View my certificates' }}</a></p>@endif
<p>{{ $ar ? 'هذه رسالة مرتبطة بحسابك أو معاملتك، وليست رسالة تسويقية.' : 'This is an account or transaction message, not marketing mail.' }}</p>
</div></body></html>
