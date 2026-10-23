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
     */
    public function __invoke(Request $request, Site $site, SiteRenderer $renderer, string $path = ''): Response|View
    {
        $internal = $request->boolean('internal');

        if (! $internal && $site->preview_expires_at?->isPast()) {
            abort(410, 'This preview has expired.');
        }

        if (! $internal && filled($site->preview_password) && ! $request->session()->get(PreviewUnlockController::sessionKey($site))) {
            return response()->view('previews.password', ['site' => $site]);
        }

        $html = $renderer->render($site, asPreview: true, path: $path);

        abort_if($html === null, 404);

        if ($path === '' && ! $request->boolean('internal')) {
            $site->recordView();
        }

        return response($html)->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
