<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case Paypal = 'paypal';
    case Stripe = 'stripe';
    case MobileMoney = 'mobile_money';
    case OrangeMoney = 'orange_money';
    case MtnMobileMoney = 'mtn_mobile_money';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'Virement bancaire',
            self::Cash => 'Espèces',
            self::Paypal => 'PayPal',
            self::Stripe => 'Carte bancaire (Stripe)',
            self::MobileMoney => 'Mobile Money',
            self::OrangeMoney => 'Orange Money',
            self::MtnMobileMoney => 'MTN Mobile Money',
            self::Other => 'Autre',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ], self::cases());
    }
}
