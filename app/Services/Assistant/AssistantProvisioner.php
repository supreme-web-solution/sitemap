<?php

namespace App\Services\Assistant;

use App\Models\Site;
use App\Models\SiteAssistant;
use App\Services\SiteBuilder\SiteCache;
use Illuminate\Support\Str;

/**
 * Creates a website's assistant with sensible defaults and keeps its knowledge up to date.
 */
class AssistantProvisioner
{
    public function __construct(
        protected AssistantVoices $voices,
        protected AssistantKnowledgeBuilder $knowledge,
    ) {}

    /**
     * Get the site's assistant, creating a switched-off one with defaults when it has none yet.
     */
    public function for(Site $site): SiteAssistant
    {
        $assistant = $site->assistant;

        if ($assistant !== null) {
            return $assistant;
        }

        $voice = $this->voices->forGender('female');

        $assistant = $site->assistant()->create([
            'team_id' => $site->team_id,
            'public_key' => Str::random(40),
            'enabled' => false,
            'name' => 'Maya',
            'voice_id' => $voice['id'],
            'voice_name' => $voice['name'],
        ]);

        $site->setRelation('assistant', $assistant);

        return $assistant;
    }

    /**
     * Rewrite the knowledge base from the website and the Google listing.
     */
    public function refreshKnowledge(SiteAssistant $assistant): SiteAssistant
    {
        $assistant->forceFill([
            'knowledge' => $this->knowledge->build($assistant->site),
            'knowledge_generated_at' => now(),
        ])->save();

        SiteCache::flush($assistant->site_id);

        return $assistant;
    }

    /**
     * The first message visitors see when none was written.
     */
    public function defaultGreeting(SiteAssistant $assistant, string $businessName): string
    {
        return __("Hi, I'm :name, the virtual assistant for :business. How can I help you today?", [
            'name' => $assistant->name,
            'business' => rtrim($businessName, '. '),
        ]);
    }
}
