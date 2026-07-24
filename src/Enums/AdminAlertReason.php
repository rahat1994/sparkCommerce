<?php

namespace Rahat1994\SparkCommerce\Enums;

use Rahat1994\SparkCommerce\Notifications\AdminPaymentAlert;

/**
 * Why an {@see AdminPaymentAlert}
 * was sent (U14). One parameterized notification class covers every
 * operational seam; the reason value selects the subject and body lang
 * strings (`sparkcommerce::sparkcommerce.mail.admin_alert.{value}`).
 */
enum AdminAlertReason: string
{
    case AmountMismatch = 'amount_mismatch';
    case LatePaymentRefunded = 'late_payment_refunded';
    case DisputeCreated = 'dispute_created';
    case RefundFailed = 'refund_failed';
    case WebhookSignatureFailing = 'webhook_signature_failing';
    case NeedsReconciliation = 'needs_reconciliation';
    case FreeOrderPlaced = 'free_order_placed';
}
