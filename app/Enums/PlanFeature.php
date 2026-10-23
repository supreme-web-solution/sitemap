<?php

namespace App\Enums;

enum PlanFeature: string
{
    case WhiteLabel = 'white_label';
    case ClientPortal = 'client_portal';
    case Messaging = 'messaging';
    case AiEditing = 'ai_editing';

    /**
     * Get the display label for the feature.
     */
    public function label(): string
    {
        return match ($this) {
            self::WhiteLabel => 'White-label (remove platform branding)',
            self::ClientPortal => 'Client portal',
            self::Messaging => 'SMS and WhatsApp',
            self::AiEditing => 'AI editing and concepts',
        };
    }
}