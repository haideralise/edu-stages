<?php

namespace App\Services;

use App\Models\EduLevel;
use App\Models\EduResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
The old service had one fat method with 6 if blocks dispatching by $handle.
I make each handle its own method, called directly by the controller.
*/

class EduAssesService
{
    public function getLevelById(int $id): ?array
    {
        $level = EduLevel::find($id);
        return $level ? $level->toArray() : null;
    }

    public function getAllLevels(): array
    {
        return EduLevel::orderBy('id')->get()->toArray();
    }

    public function addLv1(string $name): array
    {
        EduLevel::create([
            'pid' => 0,
            'name' => $name,
        ]);

        return ['status' => true, 'message' => 'Success'];
    }

    public function addLv2(string $name, int $lv1Id): array
    {
        EduLevel::create([
            'pid' => $lv1Id,
            'name' => $name,
        ]);

        return ['status' => true, 'message' => 'Success'];
    }

    public function addItem(string $name, int $lv2Id, array $itemData): array
    {
        EduLevel::create([
            'pid' => $lv2Id,
            'name' => $name,
            'data' => json_encode([
                'name' => $itemData['name'] ?? $name,
                'type' => $itemData['type'] ?? 'text',
                'item' => $itemData['item'] ?? '',
                'required' => $itemData['required'] ?? 0,
            ]),
        ]);

        return ['status' => true, 'message' => 'Success'];
    }

    public function updateItem(int $id, int $lv2Id, array $itemData, string $fileLevel = ''): array
    {
        EduLevel::where('id', $id)->update([
            'pid' => $lv2Id,
            'name' => $itemData['name'] ?? '',
            'data' => json_encode([
                'name' => $itemData['name'] ?? '',
                'type' => $itemData['type'] ?? 'text',
                'item' => $itemData['item'] ?? '',
                'required' => $itemData['required'] ?? 0,
            ]),
            'file_level' => $fileLevel,
        ]);

        return ['status' => true, 'message' => 'Success'];
    }

    public function updateLv(int $id, string $name): array
    {
        EduLevel::where('id', $id)->update(['name' => $name]);

        return ['status' => true, 'message' => 'Success'];
    }

    public function deleteLv(int $id): array
    {
        $hasChildren = EduLevel::where('pid', $id)->exists();

        if ($hasChildren) {
            return ['status' => false, 'message' => '刪除失敗, 請先刪除當前分類下所有的評估項目!'];
        }

        EduLevel::where('id', $id)->delete();

        return ['status' => true, 'message' => 'Success'];
    }

    public function updatePopupField(int $id, string $name, string $link): array
    {
        EduLevel::where('id', $id)->update([
            'name' => $name,
            'link' => $link,
        ]);

        return ['status' => true, 'message' => 'Success'];
    }

    public function getResultById(int $id): ?array
    {
        $result = EduResult::find($id);
        return $result ? $result->toArray() : null;
    }

    public function getResultFile(int $id): string
    {
        $result = EduResult::find($id);
        return $result?->exam_file ?? '';
    }

    public function upload(
        ?UploadedFile $file,
        ?string       $guid = null,
        ?string       $name = null,
        int           $chunk = 0,
        int           $chunks = 0
    ): array
    {
        $allowed = [
            'png', 'svg', 'bmp', 'jpg', 'jpeg', 'gif', 'psd',
            'pdf', 'zip', 'rar', 'tmp', '7z', 'gz', 'tar',
            'mp4', 'flv',
            'xls', 'xlsx', 'doc', 'docx', 'ppt', 'pptx',
        ];

        if (!$file && !$name) {
            return ['error' => 1, 'message' => 'Upload file not found.'];
        }

        $fileName = $name ?? ($file?->getClientOriginalName() ?? uniqid('file_'));
        $fileName = str_replace(' ', '_', strtolower($fileName));

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) ?: 'tmp';

        if (!in_array($ext, $allowed)) {
            return ['error' => 1, 'message' => 'file ext not allowed'];
        }

        if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $fileName)) {
            $fileName = md5($fileName) . '.' . $ext;
        }

        $resolvedGuid = $guid ? substr($guid, 3) : uniqid('file_');

        $directory = 'edu2/upload/' . date('Y/m/d');

        $finalName = Storage::exists("{$directory}/{$fileName}")
            ? "{$resolvedGuid}.{$ext}"
            : $fileName;

        $partPath = "{$directory}/{$finalName}.part";

        if ($chunks > 0) {
            $existing = Storage::exists($partPath) ? Storage::get($partPath) : '';
            $content = $existing . ($file ? file_get_contents($file->getRealPath()) : '');
            Storage::put($partPath, $content);

            if ($chunk < $chunks - 1) {
                return ['error' => 0, 'message' => 'Chunk received.'];
            }

            Storage::move($partPath, "{$directory}/{$finalName}");
        } else {
            Storage::putFileAs($directory, $file, $finalName);
        }

        $url = Storage::url("{$directory}/{$finalName}");

        return ['error' => 0, 'message' => 'Upload Success.', 'url' => $url];
    }
}