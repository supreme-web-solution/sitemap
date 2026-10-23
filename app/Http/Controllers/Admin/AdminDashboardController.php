<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\UsageMetric;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Plan;
use App\Models\Site;
use App\Models\Team;
use App\Models\UsageRecord;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    /**
     * Show platform-wide numbers.
     */
    public function __invoke(): Response
    {
        $period = now()->format('Y-m');

        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'users' => User::query()->count(),
                'newUsers' => User::query()->where('created_at', '>=', now()->subDays(30))->count(),
                'suspendedUsers' => User::query()->whereNotNull('suspended_at')->count(),
                'teams' => Team::query()->count(),
                'plans' => Plan::query()->count(),
                'leads' => Lead::query()->count(),
                'sites' => Site::query()->count(),
                'paidInvoices' => Invoice::query()->where('status', InvoiceStatus::Paid)->count(),
            ],
            'monthlyUsage' => collect(UsageMetric::cases())
                ->filter(fn (UsageMetric $metric) => $metric->isMonthly())
                ->map(fn (UsageMetric $metric) => [
                    'label' => $metric->label(),
                    'count' => (int) UsageRecord::query()->where('metric', $metric->value)->where('period', $period)->sum('count'),
                ])
                ->values(),
            'recentUsers' => User::query()->latest()->limit(6)->get(['id', 'name', 'email', 'created_at', 'suspended_at']),
            'planBreakdown' => Plan::query()->withCount('teams')->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
        ]);
    }
}