<?php

namespace App\Services;

use App\Models\EduLevel;
use App\Models\EduResult;
use Illuminate\Support\Collection;

/**
 * Assessment service — mirrors edu2/services/AssesService.php.
 *
 * Currently uses direct Eloquent queries as a working stub.
 * Method signatures match doc 10 §6 so another developer can
 * replace the internals without changing the calling code.
 */
class AssesService
{
    /**
     * Get all assessment levels with tree structure.
     *
     * Returns a nested collection: Course → Level → Items.
     */
    public function getAllLevels(): Collection
    {
        return EduLevel::getTree();
    }

    /**
     * Get a single assessment level by ID.
     */
    public function getLevelById(int $id): ?EduLevel
    {
        return EduLevel::find($id);
    }

    /**
     * Get a single assessment result by ID.
     */
    public function getResultById(int $id): ?EduResult
    {
        return EduResult::find($id);
    }

    /**
     * Get results for a specific student, grouped by exam_id.
     */
    public function getResultsForStudent(int $userId): Collection
    {
        return EduResult::where('user_id', $userId)->get();
    }

    /**
     * Get results for multiple students (batch).
     */
    public function getResultsForStudents(array $studentIds): Collection
    {
        return EduResult::whereIn('user_id', $studentIds)->get();
    }

    /**
     * Return exam_file value of an assessment result.
     */
    public function getResultFile(int $resultId): string
    {
        $result = EduResult::find($resultId);

        return $result?->exam_file ?? '';
    }

    /**
     * File upload (chunked). Stub — not yet implemented.
     *
     * @param  string  $type  Allowed: png, jpg, pdf, zip
     */
    public function upload(string $type = 'all'): void
    {
        // TODO: Implement with Storage::putFile() / Request::file()
        throw new \RuntimeException('AssesService::upload() not yet implemented');
    }

    /**
     * XHR handler for popup field updates. Stub — not yet implemented.
     *
     * Recommended: split into PUT /edu/asses/{id} route in Laravel.
     */
    public function popupFieldHandleRequest(): void
    {
        // TODO: Implement as dedicated route/controller action
        throw new \RuntimeException('AssesService::popupFieldHandleRequest() not yet implemented');
    }

    /**
     * Handle field POST requests. Stub — not yet implemented.
     *
     * Handles: add_lv1, add_lv2, add_item, update_item, update_lv, delete_lv.
     */
    public function handleFieldPostRequests(): void
    {
        // TODO: Implement as dedicated route/controller actions
        throw new \RuntimeException('AssesService::handleFieldPostRequests() not yet implemented');
    }
}
