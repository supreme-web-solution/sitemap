<?php

namespace App\Http\Controllers\Leads;

use App\Enums\UsageMetric;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Team;
use App\Services\Audit\BrandExtractor;
use App\Services\Audit\BrandLogoStorage;
use App\Services\Audit\NonSuccessHttpResponseException;
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
    public function store(
        Team $currentTeam,
        Lead $lead,
        UsageMeter $usage,
        BrandExtractor $extractor,
        BrandLogoStorage $logoStorage,
    ): RedirectResponse {
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
        } catch (NonSuccessHttpResponseException $exception) {
            throw ValidationException::withMessages(['website' => $exception->getMessage()]);
        }

        $site = $lead->site;
        $siteAccentUpdated = false;

        if ($site !== null && filled($brand['accent'])) {
            $content = $site->content;
            $content['accent'] = $brand['accent'];
            $site->forceFill(['content' => $content])->save();
            $siteAccentUpdated = true;
        }

        $logoSaved = filled($brand['logo']) && $logoStorage->store($lead, $brand['logo']);

        if (! $siteAccentUpdated && ! $logoSaved && blank($brand['accent']) && blank($brand['logo'])) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('No brand colour or logo was found on that site.'),
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Brand pulled from their website.'),
        ]);

        Inertia::flash('brand_pull', [
            'accent' => $brand['accent'],
            'logoUrl' => $logoSaved ? $lead->fresh()->logoUrl() : null,
            'logoSaved' => $logoSaved,
            'siteAccentUpdated' => $siteAccentUpdated,
        ]);

        return back();
    }
}
