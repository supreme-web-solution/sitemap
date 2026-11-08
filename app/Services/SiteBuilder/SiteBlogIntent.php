<?php

namespace App\Services\SiteBuilder;

use App\Models\Site;
use Illuminate\Support\Str;

/**
 * Deterministic blog post create/delete from plain-English chat.
 */
class SiteBlogIntent
{
    public function __construct(protected SiteBlogCopywriter $copywriter) {}

    /**
     * @param  array<string, mixed>  $content
     * @return array{content: array<string, mixed>, changed: bool}
     */
    public function apply(Site $site, array $content, string $instruction): array
    {
        $changed = false;

        if ($this->wantsRemoveAll($instruction)) {
            $deleted = $site->posts()->count();
            $site->posts()->delete();

            if ($deleted > 0) {
                SiteEditExtras::add(__('• Removed :count blog post(s)', ['count' => $deleted]));
                $changed = true;
            }

            return ['content' => $content, 'changed' => $changed];
        }

        $title = $this->removeTitle($instruction);

        if ($title !== null) {
            $query = $site->posts()->where(function ($builder) use ($title) {
                $builder->where('title', 'like', '%'.$title.'%')
                    ->orWhere('slug', Str::slug($title));
            });
            $count = (clone $query)->count();
            $query->delete();

            if ($count > 0) {
                SiteEditExtras::add(__('• Removed blog post matching “:title”', ['title' => $title]));
                $changed = true;
            }

            return ['content' => $content, 'changed' => $changed];
        }

        if (! $this->wantsGenerate($instruction)) {
            return ['content' => $content, 'changed' => false];
        }

        $count = $this->requestedCount($instruction);
        $drafts = $this->copywriter->draft($site->lead, $instruction, $count);
        $created = 0;

        foreach ($drafts as $draft) {
            $site->posts()->create([
                'title' => $draft['title'],
                'slug' => $this->uniqueSlug($site, $draft['title']),
                'excerpt' => $draft['excerpt'],
                'body' => $draft['body'],
                'published_at' => now(),
            ]);
            $created++;
        }

        if ($created > 0) {
            $content = $this->enableBlogPage($content);
            SiteEditExtras::add(__('• Published :count blog post(s)', ['count' => $created]));
            $changed = true;
        }

        return ['content' => $content, 'changed' => $changed];
    }

    public function matches(string $instruction): bool
    {
        return $this->wantsRemoveAll($instruction)
            || $this->removeTitle($instruction) !== null
            || $this->wantsGenerate($instruction);
    }

    public function wantsRemoveAll(string $instruction): bool
    {
        return preg_match('/\b(remove|delete|clear|wipe)\b.{0,40}\b(all|every)\b.{0,20}\b(blog|blogs|posts?|articles?)\b/i', $instruction) === 1
            || preg_match('/\b(remove|delete|clear|wipe)\b.{0,20}\b(all|every)\b.{0,40}\b(blog|blogs|posts?|articles?)\b/i', $instruction) === 1;
    }

    public function wantsGenerate(string $instruction): bool
    {
        if (preg_match('/\b(blog|blogs|posts?|articles?)\b/i', $instruction) !== 1) {
            return false;
        }

        return preg_match('/\b(generate|write|create|add|draft|publish|make)\b/i', $instruction) === 1;
    }

    public function removeTitle(string $instruction): ?string
    {
        if (preg_match('/\b(remove|delete)\b.{0,30}\b(blog|post|article)\b.{0,20}(?:called|named|titled|about)?\s*[\'\"]([^\'\"]{2,120})[\'\"]/i', $instruction, $match) === 1) {
            return trim($match[3]);
        }

        if (preg_match('/\b(remove|delete)\b.{0,20}\b(blog|post|article)\b\s+[\'\"]([^\'\"]{2,120})[\'\"]/i', $instruction, $match) === 1) {
            return trim($match[3]);
        }

        return null;
    }

    public function requestedCount(string $instruction): int
    {
        if (preg_match('/\b(\d+)\s*(blog|blogs|posts?|articles?)\b/i', $instruction, $match) === 1) {
            return max(1, min(5, (int) $match[1]));
        }

        if (preg_match('/\b(a|one)\s+(blog|post|article)\b/i', $instruction) === 1) {
            return 1;
        }

        return 3;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    protected function enableBlogPage(array $content): array
    {
        $pages = array_values($content['pages'] ?? SitePages::DEFAULT);

        if (! in_array('blog', $pages, true)) {
            $pages[] = 'blog';
            $content['pages'] = $pages;
            SiteEditExtras::add(__('• Turned on the Blog page in the menu'));
        }

        return $content;
    }

    protected function uniqueSlug(Site $site, string $source): string
    {
        $base = Str::limit(Str::slug($source) ?: 'post', 120, '');
        $slug = $base;
        $counter = 2;

        while ($site->posts()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
