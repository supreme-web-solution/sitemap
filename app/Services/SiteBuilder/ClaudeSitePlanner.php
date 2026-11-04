<?php

namespace App\Services\SiteBuilder;

use App\Ai\Agents\SitePlanDesigner;
use App\Ai\Support\SitePlanSubmission;
use Illuminate\Http\Client\ConnectionException;
use Throwable;

class ClaudeSitePlanner extends AiSitePlanner
{
    /**
     * Ask the Laravel AI site-plan agent for a plan through its submit tool.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    protected function requestPlan(string $systemPrompt, string $userPrompt, array $schema): array
    {
        unset($schema);

        $submission = new SitePlanSubmission;

        try {
            $designer = new SitePlanDesigner($submission, $systemPrompt);

            if ($this->conversationSite !== null) {
                $designer->continueOrStart($this->conversationSite->ai_conversation_id, $this->conversationSite);
            }

            $designer->prompt($userPrompt);

            if ($this->conversationSite !== null) {
                app(SiteEditConversation::class)->persistConversationId(
                    $this->conversationSite,
                    $designer->currentConversation(),
                );
            }
        } catch (Throwable $exception) {
            throw new ConnectionException($exception->getMessage(), 0, $exception);
        }

        return $submission->plan ?? [];
    }

    /**
     * The provider name used in logs.
     */
    protected function providerName(): string
    {
        return 'Laravel AI (Anthropic)';
    }
}
