<?php

namespace App\Http\Controllers;

use App\Models\EduClassUser;
use App\Models\EduResult;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoachResultController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAsCoach', EduResult::class);

        $user = $request->user();
        $isAdmin = $user->resolveRole() === 'admin';

        if ($isAdmin) {
            $results = EduResult::with('user')->get();
        } else {
            $studentIds = EduClassUser::studentIdsForTeacher($user->ID);
            $results = EduResult::with('user')->whereIn('user_id', $studentIds->all())->get();
        }

        $resultsByStudent = $results->groupBy('user_id');

        return view('coach.results', compact('resultsByStudent'));
    }
}
