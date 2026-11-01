<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\AdminDashboardService;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AdminDashboardService $dashboardService
    ) {}

    public function index(): View
    {
        return view('admin.dashboard.index', [
            'pageTitle' => 'Dashboard',
            'overview' => $this->dashboardService->getOverview(),
        ]);
    }
}