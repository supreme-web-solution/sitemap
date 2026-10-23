<?php

namespace App\Services\Outreach;

use App\Models\Team;

class DefaultFollowUpSequences
{
    /**
     * Create the starter Day 1 / 3 / 6 / 10 sequence for a team that has none yet.
     */
    public function ensureFor(Team $team): void
    {
        if ($team->followUpSequences()->exists()) {
            return;
        }

        $sequence = $team->followUpSequences()->create([
            'name' => 'Preview follow-up',
            'is_active' => true,
        ]);

        $daysFromPrevious = [1, 2, 3, 4];
        $subjects = [
            'A free website idea for {{business_name}}',
            'Did you get a chance to look at your website preview?',
            'Quick question about {{business_name}}',
            'Should I close the file for {{business_name}}?',
        ];
        $bodies = [
            "Hi {{business_name}} team,\n\nI put together a free preview of what your website could look like:\n\n{{preview_url}}\n\nHappy to tailor it with your photos and services.\n\nBest,\n{{sender_name}}\n{{team_name}}",
            "Hi {{business_name}} team,\n\nJust following up on the preview:\n\n{{preview_url}}\n\nIt only takes a minute to look through.\n\nBest,\n{{sender_name}}",
            "Hi {{business_name}} team,\n\nI wanted to check whether a new website is still useful for you in {{city}}. I can have it live quickly if the preview is close.\n\n{{preview_url}}\n\nBest,\n{{sender_name}}",
            "Hi {{business_name}} team,\n\nI will close this out unless you would like me to keep the preview available. Reply and I will leave it up.\n\n{{preview_url}}\n\nBest,\n{{sender_name}}",
        ];

        foreach ($daysFromPrevious as $index => $delay) {
            $sequence->steps()->create([
                'position' => $index,
                'delay_days' => $delay,
                'subject' => $subjects[$index],
                'body' => $bodies[$index],
            ]);
        }
    }
}
