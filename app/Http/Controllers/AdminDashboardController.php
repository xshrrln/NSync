<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use App\Models\Tenant;
use App\Support\GitHubReleaseService;
use Barryvdh\DomPDF\Facade\Pdf;

class AdminDashboardController extends Controller
{
    public function index(GitHubReleaseService $releaseService)
    {
        return view('admin.dashboard', [
            'tenantsCount' => Tenant::count(),
            'pendingCount' => Tenant::where('status', 'pending')->count(),
            'activeCount' => Tenant::where('status', 'active')->count(),
            'suspendedCount' => Tenant::where('status', 'disabled')->count(),
            'adminAuditLogs' => AdminAuditLog::query()
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->limit(12)
                ->get(),
            'releases' => collect($releaseService->releases(8)),
            'releaseFeedStatus' => $releaseService->feedStatus(),
        ]);
    }

    public function exportAuditTrail()
    {
        $logs = AdminAuditLog::query()
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $pdf = Pdf::loadView('pdf.central-audit-trail', [
            'logs' => $logs,
            'generatedAt' => now()->timezone(config('app.timezone')),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('central-audit-trail-' . now()->format('Y-m-d-His') . '.pdf');
    }
}
