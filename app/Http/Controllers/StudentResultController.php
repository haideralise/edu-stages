<?php

namespace App\Http\Controllers;

use App\Models\EduLevel;
use App\Models\EduResult;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentResultController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', EduResult::class);

        $user = $request->user();

        $tree = EduLevel::getTree();
        $results = EduResult::where('user_id', $user->ID)->get();
        $resultsByExamId = $results->groupBy('exam_id');

        return view('account.test_result', compact('tree', 'resultsByExamId'));
    }
}
