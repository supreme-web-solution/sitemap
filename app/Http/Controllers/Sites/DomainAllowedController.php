<?php

namespace App\Http\Controllers\Sites;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sites\StoreSiteDomainRequest;
use App\Models\SiteDomain;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DomainAllowedController extends Controller
{
    /**
     * Tell the HTTPS edge whether it may issue a certificate for a domain.
     *
     * Only domains with a published website answer 200, so strangers cannot
     * make the edge request certificates for names that are not ours.
     */
    public function __invoke(Request $request): Response
    {
        $secret = (string) config('sites.tls_ask_token');

        if ($secret === '' || ! hash_equals($secret, (string) $request->query('token'))) {
            abort(404);
        }

        $host = StoreSiteDomainRequest::normalize($request->query('domain'));

        $isLive = SiteDomain::query()
            ->verified()
            ->where('hostname', $host)
            ->whereHas('site', fn ($query) => $query->whereNotNull('published_at'))
            ->exists();

        abort_unless($isLive, 404);

        return response('ok');
    }
}
