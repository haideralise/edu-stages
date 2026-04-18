<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EduAdminLogService;
use App\Traits\ApiResponse;
use Illuminate\View\View;

class EduAdminLogController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly EduAdminLogService $adminLogService){}

    public function index(): View
    {
        $logs = $this->adminLogService->getLogs();

        return view('edu.admin.admin_log', compact('logs'));
    }
}
