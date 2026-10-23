<?php

namespace App\Http\Controllers\Sites;

use App\Enums\LeadActivityType;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sites\StorePreviewInterestRequest;
use App\Models\Site;
use App\Notifications\PreviewInterestNotification;
use Illuminate\Http\RedirectResponse;

class SitePreviewInterestController extends Controller
{
    /**
     * Record that the business owner replied from the public preview.
     */
    public function __invoke(StorePreviewInterestRequest $request, Site $site): RedirectResponse
    {
        $thanks = to_route('sites.preview', ['site' => $site->slug, 'thanks' => 1, 'internal' => 1]);

        // Bots tend to fill every field, so a filled honeypot is dropped silently.
        if (filled($request->validated('company_url'))) {
            return $thanks;
        }

        $lead = $site->lead;
        $name = $request->validated('name');
        $contact = $request->validated('contact');
        $message = $request->validated('message');

        $lead->recordActivity(
            LeadActivityType::PreviewInterest,
            __(':who replied from the website preview', ['who' => $name ?: $lead->name]),
            ['name' => $name, 'contact' => $contact, 'message' => $message],
        );

        if (in_array($lead->status, [LeadStatus::New, LeadStatus::SiteGenerated, LeadStatus::Contacted], true)) {
            $lead->status = LeadStatus::Interested;
        }

        $lead->next_follow_up_at = today()->addDays((int) config('prospecting.follow_up_days_after_preview_interest'));
        $lead->save();

        $site->team->owner()?->notify(new PreviewInterestNotification($lead, $name, $contact, $message));

        return $thanks;
    }
}
