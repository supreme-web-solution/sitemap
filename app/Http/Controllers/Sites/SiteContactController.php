<?php

namespace App\Http\Controllers\Sites;

use App\Actions\Sites\RecordSiteContact;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Services\Preview\PreviewLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SiteContactController extends Controller
{
    /**
     * Store a message sent from the preview contact form.
     */
    public function __invoke(Request $request, Site $site, RecordSiteContact $record): RedirectResponse
    {
        try {
            $record->handle($site, $request->all());
        } catch (ValidationException $exception) {
            return redirect(PreviewLink::page($site, 'contact', internal: $request->boolean('internal'), query: ['error' => 1]))
                ->withErrors($exception->errors())
                ->withInput();
        }

        return redirect(PreviewLink::page($site, 'contact', internal: $request->boolean('internal'), query: ['thanks' => 1]));
    }
}
