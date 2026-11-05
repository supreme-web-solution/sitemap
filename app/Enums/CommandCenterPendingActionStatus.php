<?php

namespace App\Enums;

enum CommandCenterPendingActionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Dismissed = 'dismissed';
}
