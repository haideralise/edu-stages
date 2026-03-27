<?php

namespace App\Http\Controllers;

use App\Models\EduClassUser;
use App\Models\EduResult;
use App\Models\WpUser;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoachResultController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAsCoach', EduResult::class);

        $user = $request->user();

        $studentIds = EduClassUser::studentIdsForTeacher($user->ID);

        $results = EduResult::whereIn('user_id', $studentIds->all())->get();

        $resultsByStudent = $results->groupBy('user_id');

        $students = WpUser::whereIn('ID', $studentIds)->get()->keyBy('ID');

        return view('coach.results', compact('resultsByStudent', 'students'));
    }
}
