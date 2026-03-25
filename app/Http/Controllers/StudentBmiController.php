<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBmiRequest;
use App\Http\Requests\UpdateBmiRequest;
use App\Http\Resources\BmiResource;
use App\Models\EduBmi;
use App\Models\EduClassUser;
use App\Models\WpUser;
use App\Models\WpUserMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentBmiController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', EduBmi::class);

        $user = $request->user();
        $isAdmin = $user->resolveRole() === 'admin';

        if ($isAdmin) {
            $records = EduBmi::with('user')
                ->orderByDesc('date')
                ->get()
                ->each(fn ($bmi) => $bmi->student_name = $bmi->user?->display_name ?? "Student #{$bmi->user_id}");

            $coachIds = EduClassUser::allTeacherIds();

            $adminIds = WpUserMeta::where('meta_key', 'wp_3x_capabilities')
                ->where('meta_value', 'like', '%administrator%')
                ->pluck('user_id');

            $excludeIds = $coachIds->merge($adminIds)->unique()->values()->all();

            $students = WpUser::whereNotIn('ID', $excludeIds)
                ->orderBy('display_name')
                ->get();

            $students->prepend($user);
        } else {
            $records = EduBmi::forUser($user->ID)
                ->orderByDesc('date')
                ->get();

            $students = collect();
        }

        return view('account.mybmi', compact('records', 'isAdmin', 'students'));
    }

    public function show(EduBmi $bmi): JsonResponse
    {
        $this->authorize('view', $bmi);

        return response()->json(new BmiResource($bmi));
    }

    public function store(StoreBmiRequest $request): JsonResponse|RedirectResponse
    {
        $this->authorize('create', EduBmi::class);

        $user = $request->user();
        $isAdmin = $user->resolveRole() === 'admin';

        $targetUserId = $isAdmin && $request->input('user_id')
            ? (int) $request->input('user_id')
            : $user->ID;

        $bmi = EduBmi::create([
            'user_id' => $targetUserId,
            'date'    => EduBmi::normalizeDate($request->input('date')),
            'height'  => $request->input('height'),
            'weight'  => $request->input('weight'),
            'hc'      => $request->input('hc', 0),
            'bmi'     => EduBmi::calculateBmi($request->input('height'), $request->input('weight')),
        ]);

        if ($request->expectsJson()) {
            return (new BmiResource($bmi))
                ->response()
                ->setStatusCode(201);
        }

        return redirect()->route('account.mybmi')->with('success', 'BMI record added.');
    }

    public function update(UpdateBmiRequest $request, EduBmi $bmi): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $bmi);

        $bmi->update([
            'date'   => EduBmi::normalizeDate($request->input('date')),
            'height' => $request->input('height'),
            'weight' => $request->input('weight'),
            'hc'     => $request->input('hc', 0),
            'bmi'    => EduBmi::calculateBmi($request->input('height'), $request->input('weight')),
        ]);

        if ($request->expectsJson()) {
            return (new BmiResource($bmi))->response();
        }

        return redirect()->route('account.mybmi')->with('success', 'BMI record updated.');
    }

    public function destroy(Request $request, EduBmi $bmi): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $bmi);

        $bmi->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Deleted']);
        }

        return redirect()->route('account.mybmi')->with('success', 'BMI record deleted.');
    }
}
