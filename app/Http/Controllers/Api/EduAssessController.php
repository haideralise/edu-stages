<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EduClass;
use App\Models\EduClassUser;
use App\Models\EduResult;
use App\Models\WpUser;
use App\Services\EduAssesService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EduAssessController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly EduAssesService $assesService)
    {
    }

    public function field(Request $request)
    {
        $level = $this->assesService->getAllLevels();
        $lv1Id = (int)$request->get('lv1', 0);
        $lv2Id = (int)$request->get('lv2', 0);

        return view('edu.assess.field', [
            'level' => $level,
            'lv1_id' => $lv1Id,
            'lv2_id' => $lv2Id,
        ]);
    }

    public function popupField(Request $request)
    {
        $id = (int)$request->get('id', 0);

        if (!$id) {
            abort(404);
        }

        $data = $this->assesService->getLevelById($id);

        if (!$data) {
            abort(404);
        }

        return view('edu.assess.popup_field', [
            'data' => $data,
        ]);
    }

    public function player(Request $request)
    {
        $id = (int)$request->get('id', 0);
        $file = $id ? $this->assesService->getResultFile($id) : '';

        return view('edu.assess.player', [
            'file' => $file,
        ]);
    }

    public function videoPlayer(Request $request)
    {
        $id = (int)$request->get('id', 0);
        $level = $id ? $this->assesService->getLevelById($id) : null;

        if (!$level) {
            abort(404);
        }

        return view('edu.assess.video_player', [
            'level' => $level,
        ]);
    }

    public function addLv1(Request $request): JsonResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:255']]);

        $result = $this->assesService->addLv1($request->input('name'));

        return $this->success(['message' => $result['message']]);
    }

    public function addLv2(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'lv1_id' => ['required', 'integer', 'min:1'],
        ]);

        $result = $this->assesService->addLv2(
            $request->input('name'),
            (int)$request->input('lv1_id')
        );

        return $this->success(['message' => $result['message']]);
    }

    public function addItem(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'lv2_id' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'string', 'in:text,number,time,radio,checkbox'],
            'item' => ['nullable', 'string'],
            'required' => ['nullable'],
        ]);

        $result = $this->assesService->addItem(
            $request->input('name'),
            (int)$request->input('lv2_id'),
            $request->only(['name', 'type', 'item', 'required'])
        );

        return $this->success(['message' => $result['message']]);
    }

    public function updateItem(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'lv2_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:text,number,time,radio,checkbox'],
            'item' => ['nullable', 'string'],
            'required' => ['nullable'],
            'file_level' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->assesService->updateItem(
            $id,
            (int)$request->input('lv2_id'),
            $request->only(['name', 'type', 'item', 'required']),
            (string)$request->input('file_level', '')
        );

        return $this->success(['message' => $result['message']]);
    }

    public function updateLv(Request $request, int $id): JsonResponse
    {
        $request->validate(['val' => ['required', 'string', 'max:255']]);

        $result = $this->assesService->updateLv($id, $request->input('val'));

        return $this->success(['message' => $result['message']]);
    }

    public function deleteLv(int $id): JsonResponse
    {
        $result = $this->assesService->deleteLv($id);

        if (!$result['status']) {
            return $this->error($result['message'], 'HAS_CHILDREN', 422);
        }

        return $this->success(['message' => $result['message']]);
    }

    public function updatePopupField(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'link' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->assesService->updatePopupField(
            $id,
            $request->input('name'),
            (string)$request->input('link', '')
        );

        return $this->success(['message' => $result['message']]);
    }

    public function uploader(Request $request): JsonResponse
    {
        $result = $this->assesService->upload(
            $request->file('file'),
            $request->input('guid'),
            $request->input('name'),
            (int)$request->input('chunk', 0),
            (int)$request->input('chunks', 0)
        );

        return response()->json($result);
    }

    // ─────────────────────────────────────────────────────────────
    // GET admin/assess
    // Admin: all records | Coach: own classes + current year only
    // ─────────────────────────────────────────────────────────────
    public function assess(Request $request)
    {
        /** @var \App\Models\WpUser $user */
        $user = $request->user();
        $isAdmin = $user->isAdmin();

        $data = $this->assesService->getAssessHistory();
        $levels = $this->assesService->getAllLevels();

        $class_ids = $isAdmin ? [] : $user->getCoachClassIds();

        return view('edu.result.assess', [
            'history' => $data['history'],
            'classes' => $data['classes'],
            'class_ids' => $class_ids,
            'levels' => $levels,
            'is_admin' => $isAdmin,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // DELETE admin/assess/delete?class_id=&class_month=&exam_date=
    // Admin only. Scheme D JSON.
    // ─────────────────────────────────────────────────────────────
    public function deleteAssess(Request $request): JsonResponse
    {
        $classId = (int)$request->query('class_id');
        $classMonth = trim((string)$request->query('class_month', ''));
        $examDate = trim((string)$request->query('exam_date', ''));

        if (!$classId || !$classMonth || !$examDate) {
            return $this->error('Missing parameters', 'VALIDATION_ERROR', 422);
        }

        EduResult::where('class_id', $classId)
            ->where('class_month', $classMonth)
            ->where('exam_date', $examDate)
            ->delete();

        return $this->success(['deleted' => true]);
    }

    // ─────────────────────────────────────────────────────────────
    // GET admin/assess/search-user?q=
    // AJAX for search_asses partial. Scheme D: { data: [{id,name}] }
    // ─────────────────────────────────────────────────────────────
    public function searchUser(Request $request): JsonResponse
    {
        $q = trim((string)$request->query('q', ''));
        $users = $this->assesService->searchUsers($q);

        return $this->success($users);
    }
}