<?php

namespace App\Http\Controllers;

use App\Models\EduResult;
use App\Services\CoachHistoryQueryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoachHistoryController extends Controller
{
    public function index(Request $request, CoachHistoryQueryService $queryService): View
    {
        $this->authorize('viewAsCoach', EduResult::class);

        $user = $request->user();
        $isAdmin = $user->resolveRole() === 'admin';

        $results = $queryService->getResults($user, $isAdmin, $request->input('class_year'));
        $resultsByClassMonth = $queryService->groupByClassMonth($results);
        $years = $queryService->getAvailableYears();
        $coaches = $isAdmin ? $queryService->buildCoachNameMap($results) : collect();

        return view('coach.history', compact('resultsByClassMonth', 'years', 'isAdmin', 'coaches'));
    }
}
