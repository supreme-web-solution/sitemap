<?php

namespace App\Http\Middleware;

use App\Models\Site;
use App\Models\Team;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * When a preview is opened on an agency's custom hostname, keep forms on that host
 * and block cross-team slug guessing.
 */
class EnsureBrandedPreviewHost
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $team = Team::query()->verifiedPreviewHostname($host)->first();

        if ($team === null) {
            return $next($request);
        }

        $site = $request->route('site');

        if ($site instanceof Site && $site->team_id !== $team->id) {
            abort(404);
        }

        $scheme = config('sites.force_https') ? 'https' : $request->getScheme();

        URL::forceRootUrl($scheme.'://'.$host);

        return $next($request);
    }
}
