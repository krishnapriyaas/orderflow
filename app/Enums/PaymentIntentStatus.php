<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentIntentStatus: string
{
    case Pending = 'pending';
    case RequiresAction = 'requires_action';
}
