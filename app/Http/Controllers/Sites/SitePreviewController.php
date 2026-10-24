<?php

namespace App\Http\Controllers\Sites;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Services\SiteBuilder\SiteRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SitePreviewController extends Controller
{
    /**
     * Show a page of the public live preview of a generated website.
     *
     * Signed-in members of the owning team may add `?editor=1` to get the visual editor, and
     * `?internal=1` to skip the expiry, password and view counter. For everyone else both
     * parameters change nothing about who may see the page.
     */
    public function __invoke(Request $request, Site $site, SiteRenderer $renderer, string $path = ''): Response|View
    {
        $isMember = $this->isTeamMember($request, $site);
        $editor = $isMember && $request->boolean('editor');
        $internal = $isMember && ($editor || $request->boolean('internal'));

        if (! $internal && $site->preview_expires_at?->isPast()) {
            abort(410, 'This preview has expired.');
        }

        if (! $internal && filled($site->preview_password) && ! $request->session()->get(PreviewUnlockController::sessionKey($site))) {
            return response()->view('previews.password', ['site' => $site]);
        }

        $html = $renderer->render($site, asPreview: true, path: $path, editor: $editor);

        abort_if($html === null, 404);

        if ($path === '' && ! $request->boolean('internal') && ! $editor) {
            $site->recordView();
        }

        $response = response($html)->header('X-Robots-Tag', 'noindex, nofollow');

        return $editor ? $response->header('Cache-Control', 'no-store, private') : $response;
    }

    protected function isTeamMember(Request $request, Site $site): bool
    {
        $user = $request->user();

        return $user !== null && $user->belongsToTeam($site->team);
    }
}
