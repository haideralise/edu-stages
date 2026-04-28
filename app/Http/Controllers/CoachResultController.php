<?php

namespace App\Http\Controllers;

use App\Models\EduResult;
use App\Services\CoachResultQueryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoachResultController extends Controller
{
    public function index(Request $request, CoachResultQueryService $queryService): View
    {
        $this->authorize('viewAsCoach', EduResult::class);

        $user = $request->user();
        $isAdmin = $user->resolveRole() === 'admin';

        $results = $queryService->getResults($user, $isAdmin);
        $resultsByStudent = $queryService->groupByStudent($results);

        return view('coach.results', compact('resultsByStudent'));
    }
}
