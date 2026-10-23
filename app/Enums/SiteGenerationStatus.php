<?php

namespace App\Enums;

enum SiteGenerationStatus: string
{
    case Idle = 'idle';
    case Queued = 'queued';
    case Failed = 'failed';
}
