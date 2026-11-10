<?php

namespace App\Services\Assistant;

use App\Models\SiteAssistant;
use App\Services\Simli\SimliClient;

/**
 * Whether live lip-synced talking avatars may be offered (platform + site + provider).
 */
class TalkingAvatarGate
{
    public function __construct(protected SimliClient $simli) {}

    /**
     * Platform kill-switch: when false, the editor hides the toggle and widgets stay on still mode.
     */
    public function available(): bool
    {
        return (bool) config('assistant.talking_avatar_available') && $this->simli->configured();
    }

    /**
     * Whether this website's visitors should get a live talking face right now.
     */
    public function activeFor(SiteAssistant $assistant): bool
    {
        return $this->available()
            && $assistant->talking_avatar_enabled
            && filled($assistant->simli_face_id);
    }
}
