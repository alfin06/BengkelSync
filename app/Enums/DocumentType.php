<?php

namespace App\Enums;

/**
 * Document number prefixes (SPEC section 4): `{BRANCH_CODE}/{TYPE}/{YYYY}/{NNNNN}`.
 */
enum DocumentType: string
{
    case WorkOrder = 'WO';
    case Invoice = 'INV';
    case Receipt = 'KW';
    case Purchase = 'PB';
}
