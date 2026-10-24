<?php

namespace App\Http\Controllers\Leads;

use App\Enums\UsageMetric;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Team;
use App\Services\Audit\BrandExtractor;
use App\Services\Audit\UnsafeUrlException;
use App\Services\Billing\UsageMeter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LeadBrandController extends Controller
{
    /**
     * Pull a brand colour (and logo URL) from the lead's current website.
     */
    public function store(Team $currentTeam, Lead $lead, UsageMeter $usage, BrandExtractor $extractor): RedirectResponse
    {
        if (blank($lead->website)) {
            throw ValidationException::withMessages([
                'website' => __('This lead has no website to pull a brand from.'),
            ]);
        }

        $usage->consume($currentTeam, UsageMetric::Audits);

        try {
            $brand = $extractor->extract($lead->website);
        } catch (UnsafeUrlException $exception) {
            throw ValidationException::withMessages(['website' => $exception->getMessage()]);
        } catch (ConnectionException) {
            throw ValidationException::withMessages(['website' => __('The website could not be reached.')]);
        }

        $site = $lead->site;

        if ($site !== null && filled($brand['accent'])) {
            $content = $site->content;
            $content['accent'] = $brand['accent'];
            $site->forceFill(['content' => $content])->save();
        }

        $parts = array_filter([
            filled($brand['accent']) ? __('colour :accent', ['accent' => $brand['accent']]) : null,
            filled($brand['logo']) ? __('logo :url', ['url' => $brand['logo']]) : null,
        ]);

        Inertia::flash('toast', [
            'type' => $parts === [] ? 'error' : 'success',
            'message' => $parts === []
                ? __('No brand colour or logo was found on that site.')
                : __('Pulled :parts. Apply the logo on the lead if you want to keep it.', ['parts' => implode(' · ', $parts)]),
        ]);

        return back();
    }
}
