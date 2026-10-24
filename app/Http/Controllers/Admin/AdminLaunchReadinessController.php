<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Operations\LaunchReadinessChecker;
use Inertia\Inertia;
use Inertia\Response;

class AdminLaunchReadinessController extends Controller
{
    /**
     * Show the same checks as `php artisan ops:verify` for platform admins.
     */
    public function __invoke(LaunchReadinessChecker $checker): Response
    {
        $report = $checker->run(live: false);

        return Inertia::render('admin/LaunchReadiness', [
            'report' => $report->toArray(),
            'liveCommand' => 'php artisan ops:verify --live',
        ]);
    }
}
