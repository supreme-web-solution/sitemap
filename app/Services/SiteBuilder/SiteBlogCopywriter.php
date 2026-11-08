<?php

namespace App\Services\SiteBuilder;

use App\Ai\Agents\SiteBlogPostDesigner;
use App\Models\Lead;
use App\Services\Ai\AiAvailability;
use Illuminate\Support\Str;
use Throwable;

/**
 * Drafts blog posts for AI chat (LLM when available, otherwise local templates).
 */
class SiteBlogCopywriter
{
    /**
     * @return list<array{title: string, excerpt: string, body: string}>
     */
    public function draft(Lead $lead, string $instruction, int $count): array
    {
        $count = max(1, min(5, $count));

        if (AiAvailability::anthropicConfigured()) {
            try {
                $posts = $this->fromAi($lead, $instruction, $count);

                if ($posts !== []) {
                    return array_slice($posts, 0, $count);
                }
            } catch (Throwable $exception) {
                SiteEditAiContext::record($exception);
                SiteEditLog::info('blog AI draft failed; using templates', [
                    'lead_id' => $lead->id,
                    'message' => Str::limit($exception->getMessage(), 200),
                ]);
            }
        }

        return array_slice($this->templates($lead), 0, $count);
    }

    /**
     * @return list<array{title: string, excerpt: string, body: string}>
     */
    protected function fromAi(Lead $lead, string $instruction, int $count): array
    {
        $category = $lead->category ?: 'local services';
        $city = $lead->city ?: 'your area';
        $name = $lead->name;

        $system = <<<SYS
        You write short, practical blog posts for a local business website.
        Business: {$name}. Category: {$category}. City: {$city}.
        Write exactly {$count} posts. Each body is 2–4 short paragraphs separated by blank lines.
        Keep claims modest; do not invent awards, prices, or licenses.
        Titles should be useful for local search. Excerpt max 180 characters.
        SYS;

        $response = (new SiteBlogPostDesigner($system))->prompt(
            "User request: {$instruction}\n\nDraft {$count} published blog posts for this business.",
        );

        $posts = [];

        foreach ($response['posts'] ?? [] as $post) {
            if (! is_array($post)) {
                continue;
            }

            $title = trim((string) ($post['title'] ?? ''));
            $body = trim((string) ($post['body'] ?? ''));
            $excerpt = trim((string) ($post['excerpt'] ?? ''));

            if ($title === '' || $body === '') {
                continue;
            }

            $posts[] = [
                'title' => Str::limit($title, 120, ''),
                'excerpt' => Str::limit($excerpt !== '' ? $excerpt : Str::limit($body, 180, ''), 300, ''),
                'body' => Str::limit($body, 20000, ''),
            ];
        }

        return $posts;
    }

    /**
     * @return list<array{title: string, excerpt: string, body: string}>
     */
    protected function templates(Lead $lead): array
    {
        $name = $lead->name;
        $category = Str::lower($lead->category ?: 'local services');
        $city = $lead->city ?: 'your area';
        $noun = $category;

        return [
            [
                'title' => "How to choose the right {$noun} in {$city}",
                'excerpt' => "A short checklist before you book {$category} help near {$city}.",
                'body' => "Looking for reliable {$category} in {$city}? Start with clear communication: what needs doing, when you need it, and what a finished job should look like.\n\nAsk how the work will be planned, what you will be told before anything starts, and how the space will be left when the job is done. {$name} keeps those steps straightforward so you are not guessing.\n\nIf you are comparing options, choose the team that listens first and explains next steps in plain language. That usually saves time and avoids surprises later.",
            ],
            [
                'title' => "Signs it is time to call {$name}",
                'excerpt' => "Common situations where booking {$category} help sooner is the smarter move.",
                'body' => "Small issues with {$category} tend to grow when they are ignored. If something keeps getting worse, keeps interrupting daily life, or you are not sure it is safe to leave alone, it is worth a conversation.\n\n{$name} can look at the job with you, talk through options, and agree the next step before work begins. You stay in control of timing and scope.\n\nSend a message or call when you notice the problem — catching it early is usually simpler than waiting until it becomes urgent.",
            ],
            [
                'title' => "What to expect when {$name} visits",
                'excerpt' => "A simple walkthrough of how a typical {$category} job runs from first contact to finish.",
                'body' => "After you get in touch, we talk through what you need and when a visit works. On site, we look at the job together so the plan matches the space and your priorities.\n\nWork starts only once you are clear on the approach. Questions are welcome along the way, and we check the finished result before we leave.\n\nThat is how {$name} keeps {$category} jobs in {$city} calm, tidy, and easy to understand from start to finish.",
            ],
            [
                'title' => "Seasonal {$category} tips for {$city} homes",
                'excerpt' => "Practical habits that help homes in {$city} stay ready year-round.",
                'body' => "Homes in {$city} take a little seasonal care. A quick walk-through each few months helps you spot wear, moisture, or fixtures that need attention before they become bigger jobs.\n\nMake a short list of anything that sticks, leaks, rattles, or looks worn. Share that list when you contact {$name} — it helps us prepare the right tools and give clearer next steps.\n\nGood {$category} is as much about timing as technique. Small visits through the year often beat one emergency call when everything fails at once.",
            ],
            [
                'title' => "Questions to ask before booking {$category}",
                'excerpt' => "A few plain questions that make hiring {$category} help clearer and less stressful.",
                'body' => "Before you book, ask how the job will be scoped, what you will approve first, and how mess or access will be handled. Clear answers up front usually mean a smoother day on site.\n\nAlso ask how updates are shared if something unexpected shows up. {$name} prefers to pause, explain, and agree the next step rather than push ahead quietly.\n\nThose habits protect your time and your home — and they are the standard we aim for with every {$category} visit in {$city}.",
            ],
        ];
    }
}
