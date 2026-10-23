<?php

namespace App\Http\Controllers\Sites;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sites\SavePostRequest;
use App\Models\Site;
use App\Models\SitePost;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SitePostController extends Controller
{
    /**
     * Add a blog post to the website.
     */
    public function store(SavePostRequest $request, Team $currentTeam, Site $site): RedirectResponse
    {
        $site->posts()->create([
            ...$this->attributes($request),
            'slug' => $this->uniqueSlug($site, $request->validated('slug') ?: $request->validated('title')),
            'published_at' => $request->boolean('is_published') ? now() : null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Post saved.')]);

        return back();
    }

    /**
     * Update a blog post.
     */
    public function update(SavePostRequest $request, Team $currentTeam, Site $site, SitePost $post): RedirectResponse
    {
        $attributes = $this->attributes($request);

        if (filled($request->validated('slug')) && $request->validated('slug') !== $post->slug) {
            $attributes['slug'] = $this->uniqueSlug($site, $request->validated('slug'), $post);
        }

        $attributes['published_at'] = $request->boolean('is_published')
            ? ($post->published_at ?? now())
            : null;

        $post->update($attributes);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Post saved.')]);

        return back();
    }

    /**
     * Delete a blog post.
     */
    public function destroy(Team $currentTeam, Site $site, SitePost $post): RedirectResponse
    {
        $post->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Post deleted.')]);

        return back();
    }

    /**
     * @return array{title: string, excerpt: string|null, body: string}
     */
    protected function attributes(SavePostRequest $request): array
    {
        return [
            'title' => $request->validated('title'),
            'excerpt' => $request->validated('excerpt'),
            'body' => $request->validated('body'),
        ];
    }

    /**
     * Find a slug no other post on the site is using.
     */
    protected function uniqueSlug(Site $site, string $source, ?SitePost $ignore = null): string
    {
        $base = Str::limit(Str::slug($source) ?: 'post', 120, '');
        $slug = $base;
        $counter = 2;

        while ($site->posts()->where('slug', $slug)->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
