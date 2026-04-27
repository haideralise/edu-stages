<?php

namespace Tests\Unit;

use App\Services\CoachEntranceFeeService;
use App\Services\DistrictManagementService;
use PHPUnit\Framework\TestCase;

class CoachEntranceFeeServiceTest extends TestCase
{
    private function service(): CoachEntranceFeeService
    {
        return new CoachEntranceFeeService(new DistrictManagementService);
    }

    public function test_get_timeslot_from_start_time(): void
    {
        $s = $this->service();
        $this->assertSame('morning', $s->getTimeslotFromStartTime('11:00am'));
        $this->assertSame('afternoon', $s->getTimeslotFromStartTime('2:00pm'));
        $this->assertSame('evening', $s->getTimeslotFromStartTime('6:00pm'));
        $this->assertSame('', $s->getTimeslotFromStartTime(''));
    }

    public function test_parse_start_time_delegates_to_class_date_time_parse(): void
    {
        $s = $this->service();
        $this->assertSame('11:00am', $s->parseStartTimeFromDateTime('逢觀塘星期二|11:00am-12:00pm'));
    }

    public function test_calculate_entrance_fee_dedupes_same_pool_date_timeslot(): void
    {
        $s = $this->service();
        // Same pool (觀塘), same date, same timeslot → count once for totals
        $classes = [
            'a' => [
                'class_name' => '兒童泳班-觀塘星期二|11:00am-12:00pm',
                'date_time' => '兒童泳班-觀塘星期二|11:00am-12:00pm',
                'days2' => ['2026-02-02' => ''],
            ],
            'b' => [
                'class_name' => '兒童泳班-觀塘星期四|11:00am-12:00pm',
                'date_time' => '兒童泳班-觀塘星期四|11:00am-12:00pm',
                'days2' => ['2026-02-02' => ''],
            ],
        ];
        $out = $s->calculateEntranceFee($classes, '2026-02');
        $this->assertSame(1, $out['workday_num']);
        $this->assertSame(17, $out['workday_fee']);
        $this->assertSame(17, $out['attend_fee']);
    }
}
