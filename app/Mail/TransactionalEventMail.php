<?php

namespace App\Mail;

use App\Models\TransactionalDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TransactionalEventMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public TransactionalDelivery $delivery) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subjects = [
            'welcome' => ['مرحبًا بك في أكاديمية الجزيرة', 'Welcome to JCEC Academy'],
            'order_created' => ['تم إنشاء طلبك', 'Your order was created'],
            'purchase_completed' => ['اكتمل طلبك وأصبح الوصول متاحًا', 'Your order is complete and access is ready'],
            'payment_rejected' => ['لم يُقبل إثبات الدفع', 'Your payment was not accepted'],
            'order_cancelled' => ['تم إلغاء طلبك', 'Your order was cancelled'],
            'refund_completed' => ['اكتمل الاسترداد المسجل', 'Your recorded refund is complete'],
            'refund_rejected' => ['لم تتم الموافقة على طلب الاسترداد', 'Your refund request was not approved'],
            'certificate_issued' => ['شهادتك متاحة', 'Your certificate is available'],
            'certificate_approval_granted' => ['تمت الموافقة على طلب الشهادة', 'Your certificate request was approved'],
        ];
        $pair = $subjects[$this->delivery->message_type] ?? ['تحديث من أكاديمية الجزيرة', 'JCEC Academy update'];

        return new Envelope(subject: $pair[$this->delivery->locale === 'ar' ? 0 : 1]);
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(view: 'emails.transactional', with: ['delivery' => $this->delivery]);
    }
}
