<?php

namespace App\Services\Outreach;

use App\Models\Team;

class DefaultEmailTemplates
{
    /**
     * Create the starter templates for a team that has none yet.
     */
    public function ensureFor(Team $team): void
    {
        if ($team->emailTemplates()->exists()) {
            return;
        }

        $team->emailTemplates()->createMany($this->templates());
    }

    /**
     * @return list<array{name: string, subject: string, body: string}>
     */
    public function templates(): array
    {
        return [
            [
                'name' => 'Introduction with preview',
                'subject' => 'A free website idea for {{business_name}}',
                'body' => "Hi {{business_name}} team,\n\nI came across your {{category}} business in {{city}} and noticed you could use a stronger online presence. I put together a free preview of what your website could look like:\n\n{{preview_url}}\n\nIf you like it, I can customise it with your photos and services and have it live quickly. Happy to answer any questions.\n\nBest,\n{{sender_name}}\n{{team_name}}",
            ],
            [
                'name' => 'Follow-up',
                'subject' => 'Did you get a chance to look at your website preview?',
                'body' => "Hi {{business_name}} team,\n\nJust following up on the website preview I sent over:\n\n{{preview_url}}\n\nIt only takes a minute to look through, and I can make any changes you would like.\n\nBest,\n{{sender_name}}",
            ],
            [
                'name' => 'Website improvement idea',
                'subject' => 'Quick improvement idea for {{business_name}}',
                'body' => "Hi {{business_name}} team,\n\nI took a quick look at your current website and noticed: {{top_issue}}\n\nI have prepared a redesigned preview that fixes this and makes it easier for customers in {{city}} to contact you:\n\n{{preview_url}}\n\nWould you like to have a look?\n\nBest,\n{{sender_name}}\n{{team_name}}",
            ],
        ];
    }
}
