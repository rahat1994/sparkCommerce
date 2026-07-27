<?php

namespace Rahat1994\SparkCommerce\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasLabel
{
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Refunded = 'refunded';

    /**
     * The order lifecycle state machine.
     *
     * AwaitingPayment → Paid | Expired | Cancelled
     * Paid            → Processing | Cancelled | Refunded
     * Processing      → Shipped | Refunded
     * Shipped         → Delivered
     * Delivered, Cancelled, Expired, Refunded → terminal
     */
    public function canTransitionTo(self $to): bool
    {
        return in_array($to, match ($this) {
            self::AwaitingPayment => [self::Paid, self::Expired, self::Cancelled],
            self::Paid => [self::Processing, self::Cancelled, self::Refunded],
            self::Processing => [self::Shipped, self::Refunded],
            self::Shipped => [self::Delivered],
            self::Delivered, self::Cancelled, self::Expired, self::Refunded => [],
        }, true);
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'Awaiting payment',
            self::Paid => 'Paid',
            self::Processing => 'Processing',
            self::Shipped => 'Shipped',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
            self::Refunded => 'Refunded',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::AwaitingPayment, self::Refunded => 'warning',
            self::Paid, self::Delivered => 'success',
            self::Processing, self::Shipped => 'info',
            self::Cancelled => 'danger',
            self::Expired => 'gray',
        };
    }
}
