<?php

namespace App\Enums;

enum InvoiceLineType: string
{
    case Service = 'service';
    case Part = 'part';
    // Anything else, e.g. towing or a sublet job.
    case Other = 'other';

    public function label(): string
    {
        return __('invoices.line_type.'.$this->value);
    }
}
