<?php

namespace App\Http\Controllers;

use App\Models\EduClassUser;
use App\Models\EduResult;
use App\Models\WpUser;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoachHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAsCoach', EduResult::class);

        $user = $request->user();
        $isAdmin = $user->resolveRole() === 'admin';

        if ($isAdmin) {
            $query = EduResult::query();
        } else {
            $studentIds = EduClassUser::studentIdsForTeacher($user->ID);
            $query = EduResult::whereIn('user_id', $studentIds->all());
        }

        // Filter by class_year
        if ($request->filled('class_year')) {
            $query->where('class_year', $request->input('class_year'));
        }

        $results = $query->orderByDesc('exam_date')->get();

        $studentIds = $results->pluck('user_id')->unique();
        $students = WpUser::whereIn('ID', $studentIds)->get()->keyBy('ID');

        // Available years for filter dropdown
        $years = EduResult::distinct()->pluck('class_year')->filter()->sort()->values();

        $resultsByClassMonth = $results->groupBy(fn ($r) => $r->class_year.' '.$r->class_month);

        // Build class_id → coach names map for admin view
        $coaches = collect();
        if ($isAdmin) {
            $classIds = $results->pluck('class_id')->unique();
            $classUsers = EduClassUser::whereIn('class_id', $classIds)->get();

            $teacherIds = $classUsers->flatMap(fn ($cu) => $cu->teacher ?? [])
                ->map(fn ($id) => (int) $id)->unique();
            $teacherNames = WpUser::whereIn('ID', $teacherIds)->pluck('display_name', 'ID');

            $coaches = $classUsers->groupBy('class_id')->map(function ($rows) use ($teacherNames) {
                return $rows->flatMap(fn ($cu) => $cu->teacher ?? [])
                    ->map(fn ($id) => $teacherNames->get((int) $id, 'Unknown'))
                    ->unique()->implode(', ');
            });
        }

        return view('coach.history', compact('resultsByClassMonth', 'students', 'years', 'isAdmin', 'coaches'));
    }
}
