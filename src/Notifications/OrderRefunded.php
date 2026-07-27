<?php

namespace Rahat1994\SparkCommerce\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Rahat1994\SparkCommerce\Models\SCOrder;
use Rahat1994\SparkCommerce\Models\SCRefund;

/**
 * Customer mail for a succeeded refund — full or partial (R15/U14). The
 * body always states the exact refunded amount, taken from the durable
 * refund row.
 */
class OrderRefunded extends Notification implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public SCOrder $order,
        public SCRefund $refund,
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = $this->refund->amount_cents?->formatByDecimal() ?? '0.00';

        return (new MailMessage)
            ->subject(__('sparkcommerce::sparkcommerce.mail.order_refunded.subject', [
                'order_number' => (string) $this->order->order_number,
            ]))
            ->line(__('sparkcommerce::sparkcommerce.mail.order_refunded.body', [
                'order_number' => (string) $this->order->order_number,
                'amount' => $amount . ' ' . $this->refund->currency,
            ]))
            ->line(__('sparkcommerce::sparkcommerce.mail.order_refunded.outro'));
    }
}
