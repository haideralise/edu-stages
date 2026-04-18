<?php

namespace App\Services\Common;

use App\Models\WpTermTaxonomy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from edu2/services/Common/DistrictManagementService.php.
 * Districts come from wp term_taxonomy (taxonomy=product_cat) + terms, same filters as ClassModel::get_all_districts.
 * Uses an in-memory tree (max 2 queries per request) to avoid N+1 in getAllDistrictsFlattened / generateDistrictOptions / getDistrictPath.
 */
class DistrictManagementService
{
    /**
     * @var array{top: array<int, string>, children: array<int, array<int, string>>}|null
     */
    private ?array $districtTreeCache = null;

    /**
     * @param  int  $district_id  一級地區ID
     * @return array{0: array<int, string>, 1: array<int, string>} [$district, $district2]
     */
    public function getDistricts($district_id = 0): array
    {
        $district = $this->getTopLevelDistricts();
        $district2 = $district_id ? $this->getSubDistricts($district_id) : [];

        return [$district, $district2];
    }

    /**
     * @return array<int, string> term_id => name
     */
    public function getTopLevelDistricts(): array
    {
        return $this->getDistrictTree()['top'];
    }

    /**
     * @param  int  $parent_id  父級 term_id（0 等同頂層，與 edu2 get_all_districts(0) 一致）
     * @return array<int, string>
     */
    public function getSubDistricts($parent_id): array
    {
        $pid = (int) $parent_id;
        if ($pid === 0) {
            return $this->getTopLevelDistricts();
        }

        $tree = $this->getDistrictTree();
        if (isset($tree['children'][$pid])) {
            return $tree['children'][$pid];
        }

        // 非頂層之下的子項（例如第三層）、或 cache 未涵蓋的 parent：單次查詢，避免 N+1 迴圈查 DB
        return $this->fetchDistrictsByParentIdUncached($pid);
    }

    /**
     * @return array{class_type: string, district_name: string, region: string, extracted_successfully: bool}
     */
    public function extractDistrictFromClassName($className): array
    {
        $separators = ['-', '－', '—', '–'];
        $separatorPos = false;

        foreach ($separators as $sep) {
            $pos = strpos($className, $sep);
            if ($pos !== false) {
                $separatorPos = $pos;
                break;
            }
        }

        if ($separatorPos === false) {
            return [
                'class_type' => '未知類型',
                'district_name' => '未知地區',
                'region' => '未分類',
                'extracted_successfully' => false,
            ];
        }

        $classType = trim(substr($className, 0, $separatorPos));
        $remaining = trim(substr($className, $separatorPos + 1));

        $weekPos = strpos($remaining, '星期');
        if ($weekPos !== false) {
            $districtName = trim(substr($remaining, 0, $weekPos));
        } else {
            $districtName = $remaining;
        }

        return [
            'class_type' => $classType,
            'district_name' => $districtName,
            'region' => $this->inferRegionFromDistrictName($districtName),
            'extracted_successfully' => true,
        ];
    }

    /**
     * @return array<int, string> flattened term_id => name (top then each child group)
     */
    public function getAllDistrictsFlattened(): array
    {
        $result = [];
        $tree = $this->getDistrictTree();
        foreach ($tree['top'] as $id => $name) {
            $result[$id] = $name;
            foreach ($tree['children'][$id] ?? [] as $sub_id => $sub_name) {
                $result[$sub_id] = $sub_name;
            }
        }

        return $result;
    }

    public function generateDistrictOptions($selectedId = 0, $includeSubDistricts = true): string
    {
        $html = '<option value="">請選擇區域</option>';
        $tree = $this->getDistrictTree();

        foreach ($tree['top'] as $id => $name) {
            $selected = ($id == $selectedId) ? 'selected' : '';
            $html .= "<option value=\"{$id}\" {$selected}>{$name}</option>";

            if ($includeSubDistricts) {
                foreach ($tree['children'][$id] ?? [] as $sub_id => $sub_name) {
                    $selected = ($sub_id == $selectedId) ? 'selected' : '';
                    $html .= "<option value=\"{$sub_id}\" {$selected}>　├ {$sub_name}</option>";
                }
            }
        }

        return $html;
    }

    /**
     * @return array{current: array{id: int, name: string}, parent: array{id: int, name: string}|null, level: int}
     */
    public function getDistrictPath($district_id): array
    {
        $district_id = (int) $district_id;
        $tree = $this->getDistrictTree();

        if (isset($tree['top'][$district_id])) {
            return [
                'current' => ['id' => $district_id, 'name' => $tree['top'][$district_id]],
                'parent' => null,
                'level' => 1,
            ];
        }

        foreach ($tree['top'] as $parent_id => $parent_name) {
            $subLevel = $tree['children'][$parent_id] ?? [];
            if (isset($subLevel[$district_id])) {
                return [
                    'current' => ['id' => $district_id, 'name' => $subLevel[$district_id]],
                    'parent' => ['id' => (int) $parent_id, 'name' => $parent_name],
                    'level' => 2,
                ];
            }
        }

        return [
            'current' => ['id' => $district_id, 'name' => '未知'],
            'parent' => null,
            'level' => 0,
        ];
    }

    /**
     * @return array{original_name: string, district_info: array, time_info: array, weekdays: list<string>}
     */
    public function analyzeClassName($className): array
    {
        $districtInfo = $this->extractDistrictFromClassName($className);

        $timePattern = '/(\d{1,2}:\d{2}[ap]m)-(\d{1,2}:\d{2}[ap]m)/';
        $timeInfo = [];
        if (preg_match($timePattern, $className, $matches)) {
            $timeInfo = [
                'start_time' => $matches[1],
                'end_time' => $matches[2],
                'time_slot' => $matches[1] . '-' . $matches[2],
            ];
        }

        $weekdayPattern = '/星期([一二三四五六日、]+)/';
        $weekdays = [];
        if (preg_match($weekdayPattern, $className, $matches)) {
            $weekdayStr = $matches[1];
            $weekdayMap = ['一' => '星期一', '二' => '星期二', '三' => '星期三', '四' => '星期四', '五' => '星期五', '六' => '星期六', '日' => '星期日'];
            foreach ($weekdayMap as $char => $day) {
                if (strpos($weekdayStr, $char) !== false) {
                    $weekdays[] = $day;
                }
            }
        }

        return [
            'original_name' => $className,
            'district_info' => $districtInfo,
            'time_info' => $timeInfo,
            'weekdays' => $weekdays,
        ];
    }

    /**
     * @param  string  $keyword
     * @param  int  $parent
     * @return array<int, string>
     */
    public function getDistrictsByName($keyword, $parent): array
    {
        $districts = $parent ? $this->getSubDistricts($parent) : $this->getTopLevelDistricts();

        $result = [];
        foreach ($districts as $id => $name) {
            if (strpos($name, $keyword) !== false) {
                $result[$id] = $name;
            }
        }

        return $result;
    }

    /**
     * @return list<int>
     */
    public function getDistrictTerms($district_id): array
    {
        $subDistricts = $this->getSubDistricts($district_id);

        $termIds = [];
        foreach ($subDistricts as $id => $name) {
            if (strpos($name, '泳班') !== false) {
                $termIds[] = $id;
            }
        }

        return $termIds;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getAllDistricts2(): array
    {
        if (! Schema::hasTable('edu_district')) {
            return [];
        }

        return DB::table('edu_district')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * Same semantics as edu2 ClassModel::get_term_taxonomy_by_id (single query).
     *
     * @return list<array<string, mixed>>
     */
    public function getTermTaxonomyById($district_id): array
    {
        $district_id = (int) $district_id;

        return WpTermTaxonomy::query()
            ->where('taxonomy', 'product_cat')
            ->where(function ($q) use ($district_id) {
                $q->where('parent', $district_id)
                    ->orWhere('term_taxonomy_id', $district_id)
                    ->orWhere('term_id', $district_id);
            })
            ->with('term')
            ->get()
            ->map(function (WpTermTaxonomy $tt) {
                $term = $tt->term;
                $row = $tt->getAttributes();
                $row['name'] = $term ? (string) $term->name : '';

                return $row;
            })
            ->all();
    }

    private function inferRegionFromDistrictName($districtName): string
    {
        $regionMap = [
            '觀塘' => '九龍區',
            '黃大仙' => '九龍區',
            '鑽石山' => '九龍區',
            '斧山道' => '九龍區',
            '何文田' => '九龍區',
            '紅磡' => '九龍區',
            '大環山' => '九龍區',
            '深水埗' => '九龍區',
            '荔枝角' => '九龍區',
            '九龍公園' => '九龍區',

            '沙田' => '新界區',
            '荃灣' => '新界區',
            '元朗' => '新界區',
            '屯門' => '新界區',
            '大埔' => '新界區',
            '粉嶺' => '新界區',
            '上水' => '新界區',

            '銅鑼灣' => '港島區',
            '中環' => '港島區',
            '北角' => '港島區',
            '筲箕灣' => '港島區',
            '柴灣' => '港島區',
            '西環' => '港島區',
        ];

        foreach ($regionMap as $area => $region) {
            if (strpos($districtName, $area) !== false) {
                return $region;
            }
        }

        return '未分類';
    }

    /**
     * Loads top-level + second-level product_cat districts with 游泳班 filter (two queries max).
     *
     * @return array{top: array<int, string>, children: array<int, array<int, string>>}
     */
    private function getDistrictTree(): array
    {
        if ($this->districtTreeCache !== null) {
            return $this->districtTreeCache;
        }

        $top = $this->fetchDistrictsByParentIdUncached(0);
        $topTermIds = array_keys($top);

        $childrenByParent = [];
        if ($topTermIds !== []) {
            $childRows = WpTermTaxonomy::query()
                ->where('taxonomy', 'product_cat')
                ->whereIn('parent', $topTermIds)
                ->with('term')
                ->get();

            foreach ($childRows as $tt) {
                $name = $tt->term?->name ?? '';
                if (! $this->nameContainsSwimmingClassLabel($name)) {
                    continue;
                }
                $tid = (int) $tt->term_id;
                $pid = (int) $tt->parent;
                if (! isset($childrenByParent[$pid])) {
                    $childrenByParent[$pid] = [];
                }
                $childrenByParent[$pid][$tid] = $name;
            }
        }

        $this->districtTreeCache = [
            'top' => $top,
            'children' => $childrenByParent,
        ];

        return $this->districtTreeCache;
    }

    private function nameContainsSwimmingClassLabel(string $name): bool
    {
        return strpos($name, '游泳班') !== false;
    }

    /**
     * @return array<int, string>
     */
    private function fetchDistrictsByParentIdUncached(int $parentId): array
    {
        $result = [];
        $rows = WpTermTaxonomy::query()
            ->where('taxonomy', 'product_cat')
            ->where('parent', $parentId)
            ->with('term')
            ->get();

        foreach ($rows as $tt) {
            $name = $tt->term?->name ?? '';
            if (! $this->nameContainsSwimmingClassLabel($name)) {
                continue;
            }
            $result[(int) $tt->term_id] = $name;
        }

        return $result;
    }
}
