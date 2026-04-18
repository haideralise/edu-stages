<?php

namespace App\Services;

use App\Models\EduClass;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ClassQueryService
{
    /**
     * Query edu_class rows with optional filters and pagination.
     *
     * Supported filters:
     *   - district_id  (int|array)
     *   - class_year   (string)
     *   - per_page      (int) — when present, returns a LengthAwarePaginator instead of Collection
     */
    public function getClasses(array $filters = []): Collection|LengthAwarePaginator
    {
        $query = EduClass::query();

        $this->applyFilters($query, $filters);

        if (isset($filters['per_page'])) {
            return $query->paginate((int) $filters['per_page']);
        }

        return $query->get();
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (isset($filters['district_id'])) {
            $query->byDistrict($filters['district_id']);
        }

        if (isset($filters['class_year'])) {
            $query->byYear($filters['class_year']);
        }
    }
}
