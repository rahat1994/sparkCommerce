<?php

namespace Rahat1994\SparkCommerce\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Rahat1994\SparkCommerce\Models\SCOrder;

/**
 * Admin mail for a freshly paid order (R15/U14), delivered on demand to
 * the configured `sparkcommerce.admin_email`. Queued after commit like
 * every transactional mail in the package.
 */
class NewOrderReceived extends Notification implements ShouldQueue
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
            ->subject(__('sparkcommerce::sparkcommerce.mail.new_order_received.subject', [
                'order_number' => (string) $this->order->order_number,
            ]))
            ->line(__('sparkcommerce::sparkcommerce.mail.new_order_received.body', [
                'order_number' => (string) $this->order->order_number,
            ]));

        if ($this->order->total_amount_cents !== null) {
            $mailMessage->line(__('sparkcommerce::sparkcommerce.mail.new_order_received.total', [
                'amount' => $this->order->total_amount_cents->formatByDecimal() . ' ' . $this->order->currency,
            ]));
        }

        $customerEmail = $this->order->user?->email;

        if (is_string($customerEmail) && $customerEmail !== '') {
            $mailMessage->line(__('sparkcommerce::sparkcommerce.mail.new_order_received.customer', [
                'email' => $customerEmail,
            ]));
        }

        return $mailMessage->line(__('sparkcommerce::sparkcommerce.mail.new_order_received.outro'));
    }
}
