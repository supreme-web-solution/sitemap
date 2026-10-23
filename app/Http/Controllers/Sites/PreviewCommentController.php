<?php

namespace App\Http\Controllers\Sites;

use App\Enums\LeadActivityType;
use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PreviewCommentController extends Controller
{
    /**
     * Record a comment left on a public preview.
     */
    public function store(Request $request, Site $site): RedirectResponse
    {
        $data = $request->validate([
            'author_name' => ['required', 'string', 'max:80'],
            'author_contact' => ['nullable', 'string', 'max:120'],
            'section' => ['nullable', 'string', 'max:40'],
            'body' => ['required', 'string', 'max:1000'],
            'company_url' => ['nullable', 'string', 'max:200'],
        ]);

        if (filled($data['company_url'] ?? null)) {
            return back();
        }

        $site->comments()->create([
            'author_name' => $data['author_name'],
            'author_contact' => $data['author_contact'] ?? null,
            'section' => $data['section'] ?? null,
            'body' => $data['body'],
        ]);

        $site->lead->recordActivity(
            LeadActivityType::ClientFeedback,
            __('Preview comment from :who: :text', [
                'who' => $data['author_name'],
                'text' => Str::limit($data['body'], 80),
            ]),
            ['section' => $data['section'] ?? null],
        );

        return to_route('sites.preview', ['site' => $site->slug, 'thanks' => 1, 'internal' => 1]);
    }
}
