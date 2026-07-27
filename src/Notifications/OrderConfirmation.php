<?php

namespace Rahat1994\SparkCommerce\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Rahat1994\SparkCommerce\Models\SCOrder;

/**
 * Customer mail for a freshly paid order (R15/U14): queued, and released
 * to the queue only after the surrounding transaction commits, so a
 * rolled-back transition can never mail anyone.
 */
class OrderConfirmation extends Notification implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public SCOrder $order,
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
        $mailMessage = (new MailMessage)
            ->subject(__('sparkcommerce::sparkcommerce.mail.order_confirmation.subject', [
                'order_number' => (string) $this->order->order_number,
            ]))
            ->line(__('sparkcommerce::sparkcommerce.mail.order_confirmation.body', [
                'order_number' => (string) $this->order->order_number,
            ]));

        if ($this->order->total_amount_cents !== null) {
            $mailMessage->line(__('sparkcommerce::sparkcommerce.mail.order_confirmation.total', [
                'amount' => $this->order->total_amount_cents->formatByDecimal() . ' ' . $this->order->currency,
            ]));
        }

        return $mailMessage->line(__('sparkcommerce::sparkcommerce.mail.order_confirmation.outro'));
    }
}
