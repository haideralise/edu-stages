<?php

namespace App\Http\Controllers;

use App\Models\WpUser;
use App\Services\ChartDataService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentChartController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ChartDataService $chartService) {}

    public function index(Request $request): View
    {
        $this->authorize('chart2.viewAny');

        $user = $request->user();
        $role = $user->resolveRole();

        $students = $role === 'admin' ? $this->chartService->getStudentsWithBmi() : null;

        return view('account.chart2', [
            'user' => $user,
            'isAdmin' => $role === 'admin',
            'students' => $students,
        ]);
    }

    public function chartData(Request $request): JsonResponse
    {
        $this->authorize('chart2.viewAny');

        $user = $request->user();
        $role = $user->resolveRole();
        $type = $request->input('type', 'bmi');

        $target = ($role === 'admin' && $request->filled('user_id'))
            ? WpUser::with('meta')->findOrFail($request->integer('user_id'))
            : $user->loadMissing('meta');

        $data = $type === 'result'
            ? $this->chartService->getResultChartData($target)
            : $this->chartService->getBmiChartData($target, $type);

        return $this->success($data);
    }
}
