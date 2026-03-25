<?php

namespace App\Http\Controllers;

use App\Models\EduClassUser;
use App\Models\EduResult;
use App\Models\WpUser;
use App\Services\AssesService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoachResultController extends Controller
{
    public function __construct(
        private readonly AssesService $assesService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAsCoach', EduResult::class);

        $user = $request->user();

        $studentIds = EduClassUser::studentIdsForTeacher($user->ID);

        // Get results for those students via AssesService
        $results = $this->assesService->getResultsForStudents($studentIds->all());

        // Group by student
        $resultsByStudent = $results->groupBy('user_id');

        // Get student names
        $students = WpUser::whereIn('ID', $studentIds)->get()->keyBy('ID');

        return view('coach.results', compact('resultsByStudent', 'students'));
    }
}
