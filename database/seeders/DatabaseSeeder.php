<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    // Stored after seeding so class-user assignments use correct IDs
    private int $adminId;
    private int $coachLeeId;
    private int $coachWongId;
    private array $studentIds = [];

    public function run(): void
    {
        $this->seedUsers();
        $this->seedClasses();
        $this->seedClassUsers();
        $this->seedBmi();
        $this->seedResults();
        $this->seedLevels();
    }

    // ── Users ────────────────────────────────────────────────────

    private function seedUsers(): void
    {
        $pass = Hash::make('password');

        // Admin
        $this->adminId = DB::table('users')->insertGetId([
            'user_login'    => 'admin',
            'user_pass'     => $pass,
            'user_nicename' => 'admin',
            'user_email'    => 'admin@edu.test',
            'display_name'  => 'Admin User',
        ]);
        DB::table('usermeta')->insert([
            'user_id'    => $this->adminId,
            'meta_key'   => 'wp_3x_capabilities',
            'meta_value' => serialize(['administrator' => true]),
        ]);

        // Coach Lee
        $this->coachLeeId = DB::table('users')->insertGetId([
            'user_login'    => 'coach_lee',
            'user_pass'     => $pass,
            'user_nicename' => 'coach-lee',
            'user_email'    => 'lee@edu.test',
            'display_name'  => 'Coach Lee',
        ]);
        DB::table('edu_user')->insert([
            'user_id' => $this->coachLeeId, 'note' => 'Senior swimming coach',
            'hourly_wage' => 350.00, 'class_fee' => 0,
        ]);

        // Coach Wong
        $this->coachWongId = DB::table('users')->insertGetId([
            'user_login'    => 'coach_wong',
            'user_pass'     => $pass,
            'user_nicename' => 'coach-wong',
            'user_email'    => 'wong@edu.test',
            'display_name'  => 'Coach Wong',
        ]);
        DB::table('edu_user')->insert([
            'user_id' => $this->coachWongId, 'note' => 'Junior swimming coach',
            'hourly_wage' => 280.00, 'class_fee' => 0,
        ]);

        // Students
        $students = [
            ['login' => 'student_chan', 'name' => 'Chan Tai Man',   'fee' => 800.00],
            ['login' => 'student_li',  'name' => 'Li Ka Yan',      'fee' => 800.00],
            ['login' => 'student_wong','name' => 'Wong Siu Ming',   'fee' => 900.00],
            ['login' => 'student_lam', 'name' => 'Lam Hoi Yin',    'fee' => 800.00],
            ['login' => 'student_ng',  'name' => 'Ng Chi Wai',     'fee' => 900.00],
        ];

        foreach ($students as $s) {
            $id = DB::table('users')->insertGetId([
                'user_login'    => $s['login'],
                'user_pass'     => $pass,
                'user_nicename' => $s['login'],
                'user_email'    => $s['login'] . '@edu.test',
                'display_name'  => $s['name'],
            ]);
            DB::table('edu_user')->insert([
                'user_id' => $id, 'note' => '', 'hourly_wage' => 0, 'class_fee' => $s['fee'],
            ]);
            $this->studentIds[$s['login']] = $id;
        }
    }

    // ── Classes ──────────────────────────────────────────────────

    private function seedClasses(): void
    {
        $classes = [
            ['class_name' => '鑽石山游泳初班 (Sat AM)',   'district_id' => 101, 'product_id' => 5001, 'product_name' => '游泳初班', 'date_time' => 'Sat|09:00am-10:00am', 'date_month' => ['1月-2月','3月-4月'], 'class_date' => ['2025-01-04','2025-01-11','2025-01-18','2025-01-25'], 'class_exam' => [1,2,3], 'lv3' => '鑽石山', 'class_year' => '2025'],
            ['class_name' => '鑽石山游泳中班 (Sat PM)',   'district_id' => 101, 'product_id' => 5002, 'product_name' => '游泳中班', 'date_time' => 'Sat|02:00pm-03:00pm', 'date_month' => ['1月-2月','3月-4月'], 'class_date' => ['2025-01-04','2025-01-11','2025-01-18','2025-01-25'], 'class_exam' => [4,5],   'lv3' => '鑽石山', 'class_year' => '2025'],
            ['class_name' => '黃大仙游泳初班 (Sun AM)',   'district_id' => 102, 'product_id' => 5003, 'product_name' => '游泳初班', 'date_time' => 'Sun|10:00am-11:00am', 'date_month' => ['1月-2月','3月-4月'], 'class_date' => ['2025-01-05','2025-01-12','2025-01-19','2025-01-26'], 'class_exam' => [1,2,3], 'lv3' => '黃大仙', 'class_year' => '2025'],
            ['class_name' => '將軍澳游泳高班 (Sat AM)',   'district_id' => 103, 'product_id' => 5004, 'product_name' => '游泳高班', 'date_time' => 'Sat|08:00am-09:00am', 'date_month' => ['3月-4月','5月-6月'], 'class_date' => ['2025-03-01','2025-03-08','2025-03-15','2025-03-22'], 'class_exam' => [6,7],   'lv3' => '將軍澳', 'class_year' => '2025'],
            ['class_name' => '沙田游泳初班 (Sat PM)',     'district_id' => 104, 'product_id' => 5005, 'product_name' => '游泳初班', 'date_time' => 'Sat|03:00pm-04:00pm', 'date_month' => ['5月-6月','7月-8月'], 'class_date' => ['2025-05-03','2025-05-10','2025-05-17','2025-05-24'], 'class_exam' => [1,2],   'lv3' => '沙田',   'class_year' => '2025'],
            ['class_name' => '鑽石山游泳高班 (Sun PM)',   'district_id' => 101, 'product_id' => 5006, 'product_name' => '游泳高班', 'date_time' => 'Sun|02:00pm-03:30pm', 'date_month' => ['7月-8月','9月-10月'], 'class_date' => ['2025-07-06','2025-07-13','2025-07-20','2025-07-27'], 'class_exam' => [6,7,8], 'lv3' => '鑽石山', 'class_year' => '2025'],
            ['class_name' => '黃大仙游泳中班 (Wed)',      'district_id' => 102, 'product_id' => 5007, 'product_name' => '游泳中班', 'date_time' => 'Wed|04:00pm-05:00pm', 'date_month' => ['9月-10月','11月-12月'], 'class_date' => ['2025-09-03','2025-09-10','2025-09-17','2025-09-24'], 'class_exam' => [4,5],   'lv3' => '黃大仙', 'class_year' => '2025'],
            // 2024
            ['class_name' => '鑽石山游泳初班 2024 (Sat)', 'district_id' => 101, 'product_id' => 4001, 'product_name' => '游泳初班', 'date_time' => 'Sat|09:00am-10:00am', 'date_month' => ['7月-8月','9月-10月'], 'class_date' => ['2024-07-06','2024-07-13','2024-07-20','2024-07-27'], 'class_exam' => [1,2,3], 'lv3' => '鑽石山', 'class_year' => '2024'],
            ['class_name' => '將軍澳游泳中班 2024 (Sun)', 'district_id' => 103, 'product_id' => 4002, 'product_name' => '游泳中班', 'date_time' => 'Sun|11:00am-12:00pm', 'date_month' => ['9月-10月','11月-12月'], 'class_date' => ['2024-09-01','2024-09-08','2024-09-15','2024-09-22'], 'class_exam' => [4,5],   'lv3' => '將軍澳', 'class_year' => '2024'],
            ['class_name' => '沙田游泳高班 2024 (Sat)',   'district_id' => 104, 'product_id' => 4003, 'product_name' => '游泳高班', 'date_time' => 'Sat|10:00am-11:30am', 'date_month' => ['11月-12月'],          'class_date' => ['2024-11-02','2024-11-09','2024-11-16','2024-11-23'], 'class_exam' => [6,7,8], 'lv3' => '沙田',   'class_year' => '2024'],
        ];

        foreach ($classes as $c) {
            $c['date_month'] = json_encode($c['date_month']);
            $c['class_date'] = json_encode($c['class_date']);
            $c['class_exam'] = json_encode($c['class_exam']);
            DB::table('edu_class')->insert($c);
        }
    }

    // ── Class–User assignments ────────────────────────────────────

    private function seedClassUsers(): void
    {
        $lee  = (string) $this->coachLeeId;
        $wong = (string) $this->coachWongId;
        $s    = $this->studentIds;

        // Get the first class_id from what we just seeded
        $firstClassId = DB::table('edu_class')->min('class_id');

        $assignments = [
            [
                'class_id'   => $firstClassId,
                'month'      => '1月-2月',
                'student'    => json_encode([(string)$s['student_chan'], (string)$s['student_li'], (string)$s['student_wong']]),
                'teacher'    => json_encode([$lee]),
                'days'       => '2025-01-04,2025-01-11,2025-01-18,2025-01-25',
                'class_year' => '2025',
                'sort'       => 202501,
            ],
            [
                'class_id'   => $firstClassId + 1,
                'month'      => '1月-2月',
                'student'    => json_encode([(string)$s['student_wong'], (string)$s['student_lam']]),
                'teacher'    => json_encode([$lee]),
                'days'       => '2025-01-04,2025-01-11,2025-01-18,2025-01-25',
                'class_year' => '2025',
                'sort'       => 202501,
            ],
            [
                'class_id'   => $firstClassId + 2,
                'month'      => '1月-2月',
                'student'    => json_encode([(string)$s['student_chan'], (string)$s['student_ng']]),
                'teacher'    => json_encode([$wong]),
                'days'       => '2025-01-05,2025-01-12,2025-01-19,2025-01-26',
                'class_year' => '2025',
                'sort'       => 202501,
            ],
            [
                'class_id'   => $firstClassId + 3,
                'month'      => '3月-4月',
                'student'    => json_encode([(string)$s['student_li'], (string)$s['student_lam'], (string)$s['student_ng']]),
                'teacher'    => json_encode([$wong]),
                'days'       => '2025-03-01,2025-03-08,2025-03-15,2025-03-22',
                'class_year' => '2025',
                'sort'       => 202503,
            ],
            [
                'class_id'   => $firstClassId + 4,
                'month'      => '5月-6月',
                'student'    => json_encode([(string)$s['student_chan'], (string)$s['student_li'], (string)$s['student_wong'], (string)$s['student_lam']]),
                'teacher'    => json_encode([$lee, $wong]),
                'days'       => '2025-05-03,2025-05-10,2025-05-17,2025-05-24',
                'class_year' => '2025',
                'sort'       => 202505,
            ],
        ];

        foreach ($assignments as $a) {
            DB::table('edu_class_user')->insert(array_merge([
                'student_makeup'          => null,
                'student_transfer'        => null,
                'student_order'           => null,
                'order_id'                => null,
                'class_exam'              => null,
                'history_students_status' => 0,
            ], $a));
        }
    }

    // ── BMI records ────────────────────────────────────────────────

    private function seedBmi(): void
    {
        $s = $this->studentIds;
        $baseDate = strtotime('2025-01-15');

        $records = [
            ['user_id' => $s['student_chan'], 'height' => 150, 'weight' => 45, 'hc' => 54, 'bmi' => 20.00, 'date' => $baseDate],
            ['user_id' => $s['student_chan'], 'height' => 151, 'weight' => 46, 'hc' => 54, 'bmi' => 20.17, 'date' => $baseDate + 86400 * 30],
            ['user_id' => $s['student_li'],  'height' => 140, 'weight' => 38, 'hc' => 52, 'bmi' => 19.39, 'date' => $baseDate],
            ['user_id' => $s['student_wong'],'height' => 160, 'weight' => 60, 'hc' => 56, 'bmi' => 23.44, 'date' => $baseDate],
            ['user_id' => $s['student_lam'], 'height' => 145, 'weight' => 42, 'hc' => 53, 'bmi' => 19.98, 'date' => $baseDate],
            ['user_id' => $s['student_ng'],  'height' => 155, 'weight' => 50, 'hc' => 55, 'bmi' => 20.81, 'date' => $baseDate],
        ];

        foreach ($records as $r) {
            DB::table('edu_bmi')->insert($r);
        }
    }

    // ── Result records ────────────────────────────────────────────

    private function seedResults(): void
    {
        $s = $this->studentIds;
        $firstClassId = DB::table('edu_class')->min('class_id');

        $results = [
            ['class_id' => $firstClassId, 'class_month' => '1月-2月', 'exam_id' => 1, 'user_id' => $s['student_chan'], 'first_name' => 'Chan', 'last_name' => 'Tai Man', 'exam_type' => 'score', 'exam_name' => 'Freestyle', 'exam_data' => '8', 'exam_date' => '2025-01-25', 'class_year' => '2025', 'created' => time(), 'status' => 1],
            ['class_id' => $firstClassId, 'class_month' => '1月-2月', 'exam_id' => 2, 'user_id' => $s['student_chan'], 'first_name' => 'Chan', 'last_name' => 'Tai Man', 'exam_type' => 'score', 'exam_name' => 'Backstroke', 'exam_data' => '7', 'exam_date' => '2025-01-25', 'class_year' => '2025', 'created' => time(), 'status' => 1],
            ['class_id' => $firstClassId, 'class_month' => '1月-2月', 'exam_id' => 1, 'user_id' => $s['student_li'],  'first_name' => 'Li',   'last_name' => 'Ka Yan',  'exam_type' => 'score', 'exam_name' => 'Freestyle', 'exam_data' => '9', 'exam_date' => '2025-01-25', 'class_year' => '2025', 'created' => time(), 'status' => 1],
            ['class_id' => $firstClassId + 2, 'class_month' => '1月-2月', 'exam_id' => 1, 'user_id' => $s['student_ng'], 'first_name' => 'Ng', 'last_name' => 'Chi Wai', 'exam_type' => 'score', 'exam_name' => 'Freestyle', 'exam_data' => '6', 'exam_date' => '2025-01-26', 'class_year' => '2025', 'created' => time(), 'status' => 1],
        ];

        foreach ($results as $r) {
            DB::table('edu_result')->insert($r);
        }
    }

    // ── Levels (assessment structure) ──────────────────────────────

    private function seedLevels(): void
    {
        $lv1Id = DB::table('edu_level')->insertGetId(['pid' => 0, 'name' => '游泳課程']);
        $lv2Id = DB::table('edu_level')->insertGetId(['pid' => $lv1Id, 'name' => '初級']);
        DB::table('edu_level')->insert([
            'pid' => $lv2Id, 'name' => 'Freestyle',
            'data' => json_encode(['name' => 'Freestyle', 'type' => 'score', 'item' => '', 'required' => 1]),
        ]);
        DB::table('edu_level')->insert([
            'pid' => $lv2Id, 'name' => 'Backstroke',
            'data' => json_encode(['name' => 'Backstroke', 'type' => 'score', 'item' => '', 'required' => 1]),
        ]);
    }
}
