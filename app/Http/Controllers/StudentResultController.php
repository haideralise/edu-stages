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
        $user = $request->user();

        // Get own results
        $results = EduResult::where('user_id', $user->ID)->get();

        // Build level tree and group results by exam_id (level item)
        $tree = EduLevel::getTree();
        $resultsByExamId = $results->groupBy('exam_id');

        return view('account.test_result', compact('tree', 'resultsByExamId'));
    }
}
