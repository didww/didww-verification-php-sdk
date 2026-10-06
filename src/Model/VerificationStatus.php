<?php

declare(strict_types=1);

namespace Didww\Verification\Model;

enum VerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Failed = 'failed';
    case Expired = 'expired';
    case Denied = 'denied';
}
