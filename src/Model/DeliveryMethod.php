<?php

declare(strict_types=1);

namespace Didww\Verification\Model;

enum DeliveryMethod: string
{
    case Sms = 'sms';
    case Callout = 'callout';
}
