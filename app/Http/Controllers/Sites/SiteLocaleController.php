<?php

namespace App\Http\Controllers\Sites;

use App\Http\Controllers\Controller;
use App\Jobs\TranslateSiteLocale;
use App\Models\Site;
use App\Models\Team;
use App\Services\SiteBuilder\SiteLocales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SiteLocaleController extends Controller
{
    /**
     * Queue translation for an additional locale.
     */
    public function store(Request $request, Team $currentTeam, Site $site): JsonResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'string', Rule::in(SiteLocales::codes())],
        ]);

        $locale = strtolower($data['locale']);

        if ($locale === SiteLocales::default($site->content)) {
            throw ValidationException::withMessages([
                'locale' => __('Choose a language other than the default.'),
            ]);
        }

        $content = $site->content;
        $locales = SiteLocales::extra($content);

        if (! in_array($locale, $locales, true)) {
            if (count($locales) >= (int) config('sites.max_extra_locales', 3)) {
                throw ValidationException::withMessages([
                    'locale' => __('This website already has the maximum number of extra languages.'),
                ]);
            }

            $locales[] = $locale;
            $content['locales'] = $locales;
            $site->forceFill(['content' => $content])->save();
        }

        TranslateSiteLocale::dispatch($site->fresh(), $locale);

        return response()->json([
            'message' => __('Translation queued.'),
            'locales' => SiteLocales::available($site->fresh()->content),
        ]);
    }
}
