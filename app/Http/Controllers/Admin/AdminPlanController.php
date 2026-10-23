<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlanFeature;
use App\Enums\UsageMetric;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SavePlanRequest;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminPlanController extends Controller
{
    /**
     * List plans with what each includes.
     */
    public function index(): Response
    {
        return Inertia::render('admin/plans/Index', [
            'plans' => Plan::query()->withCount('teams')->orderBy('sort_order')->orderBy('name')->get(),
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