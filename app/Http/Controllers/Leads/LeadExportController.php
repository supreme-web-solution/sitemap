<?php

namespace App\Http\Controllers\Leads;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\LeadFilterRequest;
use App\Models\Lead;
use App\Models\Team;
use App\Services\Prospecting\OpportunityScorer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadExportController extends Controller
{
    /**
     * Download the filtered leads as a CSV file.
     */
    public function __invoke(LeadFilterRequest $request, Team $currentTeam): StreamedResponse
    {
        $filters = $request->filters();

        $query = $currentTeam->leads()
            ->filteredBy($filters)
            ->sortedBy($filters['sort'] ?? null);

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Name', 'Category', 'Phone', 'Email', 'Website', 'Address', 'City', 'Rating',
                'Reviews', 'Stage', 'Opportunity score', 'Tier', 'Next follow-up', 'Maps URL',
            ]);

            $query->cursor()->each(function (Lead $lead) use ($handle) {
                fputcsv($handle, array_map($this->safeCell(...), [
                    $lead->name,
                    $lead->category,
                    $lead->phone,
                    $lead->email,
                    $lead->website,
                    $lead->address,
                    $lead->city,
                    $lead->rating,
                    $lead->reviews_count,
                    $lead->status->label(),
                    $lead->opportunity_score,
                    OpportunityScorer::tier($lead->opportunity_score),
                    $lead->next_follow_up_at?->toDateString(),
                    $lead->maps_url,
                ]));
            });

            fclose($handle);
        }, 'leads-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Neutralise spreadsheet formulas in untrusted cell values.
     */
    protected function safeCell(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
