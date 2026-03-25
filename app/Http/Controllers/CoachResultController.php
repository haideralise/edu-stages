<?php

namespace App\Http\Controllers;

use App\Models\EduClassUser;
use App\Models\WpUser;
use App\Services\AssesService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CoachResultController extends Controller
{
    public function __construct(
        private readonly AssesService $assesService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $role = $user->resolveRole();

        if ($role !== 'coach' && $role !== 'admin') {
            throw new AccessDeniedHttpException('Forbidden');
        }

        // Get class-user records where this coach is a teacher
        $coachId = (string) $user->ID;
        $classUsers = EduClassUser::all()->filter(function ($cu) use ($coachId) {
            $teachers = $cu->teacher ?? [];
            return in_array($coachId, $teachers);
        });

        // Collect unique student IDs from those classes
        $studentIds = $classUsers->flatMap(function ($cu) {
            return $cu->student ?? [];
        })->map(fn ($id) => (int) $id)->unique()->values();

        // Get results for those students via AssesService
        $results = $this->assesService->getResultsForStudents($studentIds->all());

        // Group by student
        $resultsByStudent = $results->groupBy('user_id');

        // Get student names
        $students = WpUser::whereIn('ID', $studentIds)->get()->keyBy('ID');

        return view('coach.results', compact('resultsByStudent', 'students'));
    }
}
