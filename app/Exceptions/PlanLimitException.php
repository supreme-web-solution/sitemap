<?php

namespace App\Exceptions;

use App\Enums\PlanFeature;
use App\Enums\UsageMetric;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlanLimitException extends Exception
{
    /**
     * The team has used everything its plan includes for the metric.
     */
    public static function exceeded(UsageMetric $metric, int $limit): self
    {
        return new self(__('Your plan includes :limit :metric :period. Ask your administrator to upgrade your plan.', [
            'limit' => number_format($limit),
            'metric' => strtolower($metric->label()),
            'period' => $metric->isMonthly() ? __('per month') : '',
        ]));
    }

    /**
     * The team's plan does not include the feature.
     */
    public static function featureLocked(PlanFeature $feature): self
    {
        return new self(__(':feature is not included in your plan. Ask your administrator to upgrade your plan.', ['feature' => $feature->label()]));
    }

    /**
     * Send people back with a message instead of an error page.
     */
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => $this->getMessage()], 402);
        }

        Inertia::flash('toast', ['type' => 'error', 'message' => $this->getMessage()]);

        return back();
    }
}