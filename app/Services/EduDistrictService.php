<?php

namespace App\Services;

use App\Models\WpTerm;
use App\Models\WpTermTaxonomy;

class EduDistrictService
{
    public function getDistricts(): array
    {
        return WpTerm::join('term_taxonomy as tt', 'tt.term_id', '=', 'terms.term_id')
            ->where('tt.taxonomy', 'product_cat')
            ->where('terms.name', 'like', '%游泳班%')
            ->orderBy('terms.name')
            ->select('terms.term_id', 'terms.name')
            ->get()
            ->map(fn ($row) => [
                'term_id' => $row->term_id,
                'name' => $row->name,
            ])
            ->toArray();
    }

    // TODO Stage 4+: Consider migrating to WooCommerce REST API if required.
    public function store(string $name): WpTerm
    {
        $term = WpTerm::create([
            'name' => $name,
            'slug' => $this->sanitizeSlug($name),
            'term_group' => 0,
        ]);

        WpTermTaxonomy::create([
            'term_id' => $term->term_id,
            'taxonomy' => 'product_cat',
            'description' => '',
            'parent' => 0,
            'count' => 0,
        ]);

        return $term;
    }

    // TODO Stage 4+: Consider migrating to WooCommerce REST API if required.
    public function update(int $termId, string $name): WpTerm
    {
        $term = WpTerm::join('term_taxonomy as tt', 'tt.term_id', '=', 'terms.term_id')
            ->where('tt.taxonomy', 'product_cat')
            ->where('terms.term_id', $termId)
            ->select('terms.*')
            ->firstOrFail();

        $term->update([
            'name' => $name,
            'slug' => $this->sanitizeSlug($name),
        ]);

        return $term;
    }

    private function sanitizeSlug(string $name): string
    {
        $slug = mb_strtolower($name);
        $slug = preg_replace('/\s+/', '-', $slug);
        $slug = preg_replace('/[^\w\x{4e00}-\x{9fa5}-]/u', '', $slug);

        return $slug ?: 'district-' . now()->timestamp;
    }
}
