<?php

namespace App\Http\Controllers;

use App\Services\AssesService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentResultController extends Controller
{
    public function __construct(
        private readonly AssesService $assesService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $tree = $this->assesService->getAllLevels();
        $results = $this->assesService->getResultsForStudent($user->ID);
        $resultsByExamId = $results->groupBy('exam_id');

        return view('account.test_result', compact('tree', 'resultsByExamId'));
    }
}
