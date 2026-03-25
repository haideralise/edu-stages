<?php

namespace Tests\Unit;

use App\Models\EduLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EduLevelTest extends TestCase
{
    use RefreshDatabase;

    private function seedTree(): int
    {
        $courseId = DB::table('wp_3x_edu_level')->insertGetId(['pid' => 0, 'name' => 'Swimming']);
        $levelId = DB::table('wp_3x_edu_level')->insertGetId(['pid' => $courseId, 'name' => 'Beginner']);
        DB::table('wp_3x_edu_level')->insert([
            ['pid' => $levelId, 'name' => 'Freestyle 25m', 'data' => json_encode(['max_score' => 10])],
            ['pid' => $levelId, 'name' => 'Backstroke 25m', 'data' => json_encode(['max_score' => 10])],
        ]);

        return $courseId;
    }

    public function test_get_tree_returns_root_nodes(): void
    {
        $this->seedTree();

        $tree = EduLevel::getTree();
        $this->assertCount(1, $tree);
        $this->assertEquals('Swimming', $tree->first()->name);
    }

    public function test_tree_has_nested_children(): void
    {
        $this->seedTree();

        $tree = EduLevel::getTree();
        $course = $tree->first();

        $this->assertCount(1, $course->descendants);
        $level = $course->descendants->first();
        $this->assertEquals('Beginner', $level->name);
        $this->assertCount(2, $level->descendants);
    }

    public function test_parent_relationship(): void
    {
        $this->seedTree();

        $item = EduLevel::where('name', 'Freestyle 25m')->first();
        $this->assertEquals('Beginner', $item->parent->name);
    }

    public function test_children_relationship(): void
    {
        $this->seedTree();

        $course = EduLevel::where('name', 'Swimming')->first();
        $this->assertCount(1, $course->children);
        $this->assertEquals('Beginner', $course->children->first()->name);
    }

    public function test_data_cast_to_array(): void
    {
        $this->seedTree();

        $item = EduLevel::where('name', 'Freestyle 25m')->first();
        $this->assertIsArray($item->data);
        $this->assertEquals(10, $item->data['max_score']);
    }
}
