<?php

namespace Rahat1994\SparkCommerce\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Rahat1994\SparkCommerce\Enums\AdminAlertReason;

/**
 * The single parameterized admin alert for every operational payment seam
 * (R15/U14): amount mismatches, disputes, refund failures, failing
 * webhook signatures, reconciliation flags, late-payment auto-refunds and
 * free orders all share this class — the {@see AdminAlertReason} selects
 * the subject and body lang strings, `$context` fills their placeholders.
 *
 * The context is a plain scalar array on purpose: the queued payload
 * never serializes a model, so the alert still delivers even when the
 * row it talks about is gone by the time the queue runs.
 */
class AdminPaymentAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, int|string>  $context  lang replacements for the reason's strings
     */
    public function __construct(
        public AdminAlertReason $reason,
        public array $context = [],
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
            ->subject(__("sparkcommerce::sparkcommerce.mail.admin_alert.{$this->reason->value}.subject", $this->context))
            ->line(__("sparkcommerce::sparkcommerce.mail.admin_alert.{$this->reason->value}.body", $this->context));
    }
}
