<?php

namespace App\Http\Controllers\Reports;

use App\Enums\InvoiceStatus;
use App\Enums\LeadStatus;
use App\Enums\LostReason;
use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Revenue, outstanding invoices, forecast and stage conversion for the team.
     */
    public function __invoke(Request $request, Team $currentTeam): Response
    {
        $invoices = $currentTeam->invoices();
        $leads = $currentTeam->leads();

        $stageCounts = $leads->clone()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $lostReasons = $leads->clone()->where('status', LeadStatus::Lost)->whereNotNull('lost_reason')
            ->selectRaw('lost_reason, count(*) as total')->groupBy('lost_reason')->pluck('total', 'lost_reason');

        $forecastCents = $currentTeam->recurringInvoices()
            ->where('is_active', true)
            ->get()
            ->sum(fn ($schedule) => collect($schedule->items)->sum(fn (array $item) => $item['quantity'] * $item['unit_cents']));

        return Inertia::render('reports/Index', [
            'revenue' => [
                'paidThisMonthCents' => (int) $invoices->clone()->where('status', InvoiceStatus::Paid)->where('paid_at', '>=', now()->startOfMonth())->sum('total_cents'),
                'outstandingCents' => (int) $invoices->clone()->outstanding()->sum('total_cents'),
                'refundedThisMonthCents' => (int) $invoices->clone()->where('status', InvoiceStatus::Refunded)->where('refunded_at', '>=', now()->startOfMonth())->sum('total_cents'),
                'recurringMonthlyCents' => (int) $forecastCents,
            ],
            'pipeline' => collect(LeadStatus::cases())->map(fn (LeadStatus $status) => [
                'status' => $status->value,
                'label' => $status->label(),
                'count' => (int) ($stageCounts[$status->value] ?? 0),
            ])->values(),
            'lostReasons' => collect(LostReason::cases())->map(fn (LostReason $reason) => [
                'reason' => $reason->value,
                'label' => $reason->label(),
                'count' => (int) ($lostReasons[$reason->value] ?? 0),
            ])->values(),
            'conversion' => $this->conversion((int) ($stageCounts[LeadStatus::New->value] ?? 0) + (int) ($stageCounts[LeadStatus::SiteGenerated->value] ?? 0), (int) ($stageCounts[LeadStatus::Won->value] ?? 0), (int) $leads->clone()->count()),
        ]);
    }

    /**
     * @return array{wonRate: float, sample: int}
     */
    protected function conversion(int $opened, int $won, int $sample): array
    {
        $base = max($opened + $won, 1);

        return [
            'wonRate' => round(($won / max($sample, 1)) * 100, 1),
            'sample' => $sample,
        ];
    }
}
