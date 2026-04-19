<?php

namespace Tests\Unit;

use App\Models\EduLevel;
use App\Services\EduAssesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EduAssesServiceTest extends TestCase
{
    use RefreshDatabase;

    private EduAssesService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EduAssesService();
    }

    #[Test]
    public function add_lv1_inserts_root_level_with_pid_zero(): void
    {
        $result = $this->service->addLv1('游泳課程');

        $this->assertTrue($result['status']);

        $this->assertDatabaseHas('edu_level', [
            'name' => '游泳課程',
            'pid'  => 0,
        ]);
    }

    #[Test]
    public function add_lv2_inserts_level_under_correct_parent(): void
    {
        $lv1 = EduLevel::create(['pid' => 0, 'name' => '游泳課程']);

        $result = $this->service->addLv2('初級', $lv1->id);

        $this->assertTrue($result['status']);

        $this->assertDatabaseHas('edu_level', [
            'name' => '初級',
            'pid'  => $lv1->id,
        ]);
    }

    #[Test]
    public function add_item_inserts_item_with_json_data_under_correct_parent(): void
    {
        $lv1 = EduLevel::create(['pid' => 0, 'name' => '游泳課程']);
        $lv2 = EduLevel::create(['pid' => $lv1->id, 'name' => '初級']);

        $result = $this->service->addItem('遊泳時間', $lv2->id, [
            'name'     => '遊泳時間',
            'type'     => 'time',
            'item'     => '',
            'required' => 1,
        ]);

        $this->assertTrue($result['status']);

        $item = EduLevel::where('pid', $lv2->id)->where('name', '遊泳時間')->first();

        $this->assertNotNull($item);

        $data = json_decode($item->data, true);
        $this->assertEquals('time', $data['type']);
        $this->assertEquals(1, $data['required']);
    }

    #[Test]
    public function update_item_updates_name_and_data_json(): void
    {
        $lv1  = EduLevel::create(['pid' => 0, 'name' => '游泳課程']);
        $lv2  = EduLevel::create(['pid' => $lv1->id, 'name' => '初級']);
        $item = EduLevel::create([
            'pid'  => $lv2->id,
            'name' => '舊名稱',
            'data' => json_encode(['name' => '舊名稱', 'type' => 'text', 'item' => '', 'required' => 0]),
        ]);

        $result = $this->service->updateItem($item->id, $lv2->id, [
            'name'     => '遊泳時間',
            'type'     => 'number',
            'item'     => '',
            'required' => 1,
        ], 'path/to/file.pdf');

        $this->assertTrue($result['status']);

        $updated = EduLevel::find($item->id);
        $this->assertEquals('遊泳時間', $updated->name);
        $this->assertEquals('path/to/file.pdf', $updated->file_level);

        $this->assertEquals('number', $updated->data['type']);
        $this->assertEquals(1, $updated->data['required']);
    }

    #[Test]
    public function update_lv_updates_only_the_name(): void
    {
        $lv1 = EduLevel::create(['pid' => 0, 'name' => '舊課程名']);

        $result = $this->service->updateLv($lv1->id, '新課程名');

        $this->assertTrue($result['status']);

        $this->assertDatabaseHas('edu_level', [
            'id'   => $lv1->id,
            'name' => '新課程名',
            'pid'  => 0,  // pid must not change
        ]);
    }

    #[Test]
    public function delete_lv_deletes_node_when_no_children_exist(): void
    {
        $lv1 = EduLevel::create(['pid' => 0, 'name' => '游泳課程']);

        $result = $this->service->deleteLv($lv1->id);

        $this->assertTrue($result['status']);

        $this->assertDatabaseMissing('edu_level', ['id' => $lv1->id]);
    }

    #[Test]
    public function delete_lv_refuses_deletion_when_children_exist(): void
    {
        $lv1 = EduLevel::create(['pid' => 0, 'name' => '游泳課程']);
        EduLevel::create(['pid' => $lv1->id, 'name' => '初級']); // child exists

        $result = $this->service->deleteLv($lv1->id);

        $this->assertFalse($result['status']);

        $this->assertDatabaseHas('edu_level', ['id' => $lv1->id]);
    }
}