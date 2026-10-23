<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case Active = 'active';
    case Stopped = 'stopped';
    case Completed = 'completed';
}
