<?php

namespace Rahat1994\SparkCommerce\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Rahat1994\SparkCommerce\Models\SCProduct;

/**
 * Customer mail when their cart quantity exceeded the managed stock of a
 * product whose backorder policy is AllowNotifyCustomer (R15/U14). Only
 * sent when the cart belongs to a signed-in user — anonymous carts have
 * nobody to mail.
 */
class BackorderNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public SCProduct $product,
        public int $quantity,
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
        return (new MailMessage)
            ->subject(__('sparkcommerce::sparkcommerce.mail.backorder.subject', [
                'product' => (string) $this->product->name,
            ]))
            ->line(__('sparkcommerce::sparkcommerce.mail.backorder.body', [
                'product' => (string) $this->product->name,
                'quantity' => $this->quantity,
            ]))
            ->line(__('sparkcommerce::sparkcommerce.mail.backorder.outro'));
    }
}
