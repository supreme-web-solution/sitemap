<?php

namespace App\Enums;

enum CommandCenterMessageSource: string
{
    case Web = 'web';
    case WhatsApp = 'whatsapp';
}
