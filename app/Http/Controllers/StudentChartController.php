<?php

namespace App\Http\Controllers;

use App\Models\EduBmi;
use App\Models\WpUser;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentChartController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('chart2.viewAny');

        $user = $request->user();
        $role = $user->resolveRole();

        $students = null;

        if ($role === 'admin') {
            $studentIds = EduBmi::distinct()->pluck('user_id');
            $students = WpUser::whereIn('ID', $studentIds)->get();
        }

        return view('account.chart2', [
            'user' => $user,
            'isAdmin' => $role === 'admin',
            'students' => $students,
        ]);
    }
}
