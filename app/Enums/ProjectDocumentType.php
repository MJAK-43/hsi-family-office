<?php

namespace App\Enums;

enum ProjectDocumentType: string
{
    case BusinessPlan = 'business_plan';
    case FinancialForecast = 'financial_forecast';
    case IdentityDocument = 'identity_document';
    case Quote = 'quote';
    case Invoice = 'invoice';
    case Presentation = 'presentation';
    case LegalDocument = 'legal_document';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BusinessPlan => 'Business plan',
            self::FinancialForecast => 'Prévisions financières',
            self::IdentityDocument => 'Pièce d\'identité',
            self::Quote => 'Devis',
            self::Invoice => 'Facture',
            self::Presentation => 'Présentation',
            self::LegalDocument => 'Document juridique',
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
