<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlanFeature;
use App\Enums\UsageMetric;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SavePlanRequest;
use App\Models\Plan;
use App\Models\Team;
use App\Models\UsageRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminPlanController extends Controller
{
    /**
     * List plans with what each includes, which teams use them, and usage totals.
     */
    public function index(): Response
    {
        $period = now()->format('Y-m');

        $plans = Plan::query()
            ->withCount('teams')
            ->with(['teams' => fn ($query) => $query
                ->withCount(['members', 'leads', 'sites'])
                ->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (Plan $plan) use ($period) {
                $teamIds = $plan->teams->modelKeys();

                $usageThisMonth = $teamIds === []
                    ? collect()
                    : UsageRecord::query()
                        ->whereIn('team_id', $teamIds)
                        ->where('period', $period)
                        ->selectRaw('metric, SUM(count) as total')
                        ->groupBy('metric')
                        ->pluck('total', 'metric');

                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'description' => $plan->description,
                    'price_cents' => $plan->price_cents,
                    'is_default' => $plan->is_default,
                    'is_active' => $plan->is_active,
                    'limits' => $plan->limits ?? [],
                    'features' => $plan->features ?? [],
                    'teams_count' => $plan->teams_count,
                    'teams' => $plan->teams->map(fn (Team $team) => [
                        'id' => $team->id,
                        'name' => $team->name,
                        'slug' => $team->slug,
                        'is_personal' => $team->is_personal,
                        'owner' => $team->owner()?->email,
                        'members_count' => $team->members_count,
                        'leads_count' => $team->leads_count,
                        'sites_count' => $team->sites_count,
                    ])->values()->all(),
                    'analytics' => [
                        'teams_count' => (int) $plan->teams_count,
                        'members_count' => (int) $plan->teams->sum('members_count'),
                        'leads_count' => (int) $plan->teams->sum('leads_count'),
                        'sites_count' => (int) $plan->teams->sum('sites_count'),
                        'usage_this_month' => collect(UsageMetric::cases())
                            ->filter(fn (UsageMetric $metric) => $metric->isMonthly())
                            ->map(fn (UsageMetric $metric) => [
                                'key' => $metric->value,
                                'label' => $metric->label(),
                                'used' => (int) ($usageThisMonth[$metric->value] ?? 0),
                            ])
                            ->values()
                            ->all(),
                    ],
                ];
            })
            ->values()
            ->all();

        return Inertia::render('admin/plans/Index', [
            'plans' => $plans,
            'metrics' => collect(UsageMetric::cases())->map(fn (UsageMetric $metric) => [
                'key' => $metric->value,
                'label' => $metric->label(),
                'period' => $metric->isMonthly() ? 'per month' : 'in total',
            ])->all(),
            'features' => collect(PlanFeature::cases())->map(fn (PlanFeature $feature) => [
                'key' => $feature->value,
                'label' => $feature->label(),
            ])->all(),
        ]);
    }

    public function store(SavePlanRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $attributes = $request->planAttributes();

            if ($attributes['is_default']) {
                Plan::query()->update(['is_default' => false]);
            }

            Plan::create($attributes + ['sort_order' => (int) Plan::query()->max('sort_order') + 1]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plan created.')]);

        return back();
    }

    public function update(SavePlanRequest $request, Plan $plan): RedirectResponse
    {
        DB::transaction(function () use ($request, $plan) {
            $attributes = $request->planAttributes();

            if ($attributes['is_default']) {
                Plan::query()->whereKeyNot($plan->id)->update(['is_default' => false]);
            }

            $plan->update($attributes);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plan updated.')]);

        return back();
    }

    /**
     * Delete a plan. Teams on it fall back to the default plan.
     */
    public function destroy(Plan $plan): RedirectResponse
    {
        $plan->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plan deleted.')]);

        return back();
    }
}
