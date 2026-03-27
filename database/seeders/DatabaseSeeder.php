<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    private int $adminId;

    private int $coachLeeId;

    private int $coachWongId;

    private array $studentIds = [];

    public function run(): void
    {
        $this->seedUsers();
        $this->seedClasses();
        $this->seedClassUsers();
        $this->seed2024ClassUsers();
        $this->seedLevels();
        $this->seedBmiRecords();
        $this->seedResults();
    }

    // ── Users ────────────────────────────────────────────────────

    private function seedUsers(): void
    {
        $pass = Hash::make('password');

        // Admin
        $this->adminId = DB::table('users')->insertGetId([
            'user_login' => 'admin',
            'user_pass' => $pass,
            'user_nicename' => 'admin',
            'user_email' => 'admin@edu.test',
            'display_name' => 'Admin User',
        ]);
        DB::table('usermeta')->insert([
            'user_id' => $this->adminId,
            'meta_key' => 'wp_3x_capabilities',
            'meta_value' => serialize(['administrator' => true]),
        ]);

        // Coach Lee
        $this->coachLeeId = DB::table('users')->insertGetId([
            'user_login' => 'coach_lee',
            'user_pass' => $pass,
            'user_nicename' => 'coach-lee',
            'user_email' => 'lee@edu.test',
            'display_name' => 'Coach Lee',
        ]);
        DB::table('edu_user')->insert([
            'user_id' => $this->coachLeeId, 'note' => 'Senior swimming coach',
            'hourly_wage' => 350.00, 'class_fee' => 0,
        ]);

        // Coach Wong
        $this->coachWongId = DB::table('users')->insertGetId([
            'user_login' => 'coach_wong',
            'user_pass' => $pass,
            'user_nicename' => 'coach-wong',
            'user_email' => 'wong@edu.test',
            'display_name' => 'Coach Wong',
        ]);
        DB::table('edu_user')->insert([
            'user_id' => $this->coachWongId, 'note' => 'Junior swimming coach',
            'hourly_wage' => 280.00, 'class_fee' => 0,
        ]);

        // Students
        $students = [
            ['login' => 'student_chan', 'name' => 'Chan Tai Man',  'fee' => 800.00, 'birthdate' => '2010-05-12', 'gender' => 'male'],
            ['login' => 'student_li',  'name' => 'Li Ka Yan',     'fee' => 800.00, 'birthdate' => '2011-08-23', 'gender' => 'female'],
            ['login' => 'student_wong', 'name' => 'Wong Siu Ming',  'fee' => 900.00, 'birthdate' => '2009-11-03', 'gender' => 'male'],
            ['login' => 'student_lam', 'name' => 'Lam Hoi Yin',   'fee' => 800.00, 'birthdate' => '2012-02-17', 'gender' => 'female'],
            ['login' => 'student_ng',  'name' => 'Ng Chi Wai',    'fee' => 900.00, 'birthdate' => '2008-07-30', 'gender' => 'male'],
        ];

        foreach ($students as $s) {
            $id = DB::table('users')->insertGetId([
                'user_login' => $s['login'],
                'user_pass' => $pass,
                'user_nicename' => $s['login'],
                'user_email' => $s['login'].'@edu.test',
                'display_name' => $s['name'],
            ]);
            DB::table('edu_user')->insert([
                'user_id' => $id, 'note' => '', 'hourly_wage' => 0, 'class_fee' => $s['fee'],
            ]);
            DB::table('usermeta')->insert([
                ['user_id' => $id, 'meta_key' => 'billing_birthdate', 'meta_value' => $s['birthdate']],
                ['user_id' => $id, 'meta_key' => 'billing_gender',    'meta_value' => $s['gender']],
            ]);
            $this->studentIds[$s['login']] = $id;
        }
    }

    // ── Classes ──────────────────────────────────────────────────

    private function seedClasses(): void
    {
        $classes = [
            ['class_name' => '鑽石山游泳初班 (Sat AM)',   'district_id' => 101, 'product_id' => 5001, 'product_name' => '游泳初班', 'date_time' => 'Sat|09:00am-10:00am', 'date_month' => ['1月-2月', '3月-4月'], 'class_date' => ['2025-01-04', '2025-01-11', '2025-01-18', '2025-01-25'], 'class_exam' => [1, 2, 3], 'lv3' => '鑽石山', 'class_year' => '2025'],
            ['class_name' => '鑽石山游泳中班 (Sat PM)',   'district_id' => 101, 'product_id' => 5002, 'product_name' => '游泳中班', 'date_time' => 'Sat|02:00pm-03:00pm', 'date_month' => ['1月-2月', '3月-4月'], 'class_date' => ['2025-01-04', '2025-01-11', '2025-01-18', '2025-01-25'], 'class_exam' => [4, 5],   'lv3' => '鑽石山', 'class_year' => '2025'],
            ['class_name' => '黃大仙游泳初班 (Sun AM)',   'district_id' => 102, 'product_id' => 5003, 'product_name' => '游泳初班', 'date_time' => 'Sun|10:00am-11:00am', 'date_month' => ['1月-2月', '3月-4月'], 'class_date' => ['2025-01-05', '2025-01-12', '2025-01-19', '2025-01-26'], 'class_exam' => [1, 2, 3], 'lv3' => '黃大仙', 'class_year' => '2025'],
            ['class_name' => '將軍澳游泳高班 (Sat AM)',   'district_id' => 103, 'product_id' => 5004, 'product_name' => '游泳高班', 'date_time' => 'Sat|08:00am-09:00am', 'date_month' => ['3月-4月', '5月-6月'], 'class_date' => ['2025-03-01', '2025-03-08', '2025-03-15', '2025-03-22'], 'class_exam' => [6, 7],   'lv3' => '將軍澳', 'class_year' => '2025'],
            ['class_name' => '沙田游泳初班 (Sat PM)',     'district_id' => 104, 'product_id' => 5005, 'product_name' => '游泳初班', 'date_time' => 'Sat|03:00pm-04:00pm', 'date_month' => ['5月-6月', '7月-8月'], 'class_date' => ['2025-05-03', '2025-05-10', '2025-05-17', '2025-05-24'], 'class_exam' => [1, 2],   'lv3' => '沙田',   'class_year' => '2025'],
            ['class_name' => '鑽石山游泳高班 (Sun PM)',   'district_id' => 101, 'product_id' => 5006, 'product_name' => '游泳高班', 'date_time' => 'Sun|02:00pm-03:30pm', 'date_month' => ['7月-8月', '9月-10月'], 'class_date' => ['2025-07-06', '2025-07-13', '2025-07-20', '2025-07-27'], 'class_exam' => [6, 7, 8], 'lv3' => '鑽石山', 'class_year' => '2025'],
            ['class_name' => '黃大仙游泳中班 (Wed)',      'district_id' => 102, 'product_id' => 5007, 'product_name' => '游泳中班', 'date_time' => 'Wed|04:00pm-05:00pm', 'date_month' => ['9月-10月', '11月-12月'], 'class_date' => ['2025-09-03', '2025-09-10', '2025-09-17', '2025-09-24'], 'class_exam' => [4, 5],   'lv3' => '黃大仙', 'class_year' => '2025'],
            // 2024
            ['class_name' => '鑽石山游泳初班 2024 (Sat)', 'district_id' => 101, 'product_id' => 4001, 'product_name' => '游泳初班', 'date_time' => 'Sat|09:00am-10:00am', 'date_month' => ['7月-8月', '9月-10月'], 'class_date' => ['2024-07-06', '2024-07-13', '2024-07-20', '2024-07-27'], 'class_exam' => [1, 2, 3], 'lv3' => '鑽石山', 'class_year' => '2024'],
            ['class_name' => '將軍澳游泳中班 2024 (Sun)', 'district_id' => 103, 'product_id' => 4002, 'product_name' => '游泳中班', 'date_time' => 'Sun|11:00am-12:00pm', 'date_month' => ['9月-10月', '11月-12月'], 'class_date' => ['2024-09-01', '2024-09-08', '2024-09-15', '2024-09-22'], 'class_exam' => [4, 5],   'lv3' => '將軍澳', 'class_year' => '2024'],
            ['class_name' => '沙田游泳高班 2024 (Sat)',   'district_id' => 104, 'product_id' => 4003, 'product_name' => '游泳高班', 'date_time' => 'Sat|10:00am-11:30am', 'date_month' => ['11月-12月'],          'class_date' => ['2024-11-02', '2024-11-09', '2024-11-16', '2024-11-23'], 'class_exam' => [6, 7, 8], 'lv3' => '沙田',   'class_year' => '2024'],
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
        $lee = (string) $this->coachLeeId;
        $wong = (string) $this->coachWongId;
        $s = $this->studentIds;

        $firstClassId = DB::table('edu_class')->min('class_id');

        $assignments = [
            [
                'class_id' => $firstClassId,
                'month' => '1月-2月',
                'student' => json_encode([(string) $s['student_chan'], (string) $s['student_li'], (string) $s['student_wong']]),
                'teacher' => json_encode([$lee]),
                'days' => '2025-01-04,2025-01-11,2025-01-18,2025-01-25',
                'class_year' => '2025',
                'sort' => 202501,
            ],
            [
                'class_id' => $firstClassId + 1,
                'month' => '1月-2月',
                'student' => json_encode([(string) $s['student_wong'], (string) $s['student_lam']]),
                'teacher' => json_encode([$lee]),
                'days' => '2025-01-04,2025-01-11,2025-01-18,2025-01-25',
                'class_year' => '2025',
                'sort' => 202501,
            ],
            [
                'class_id' => $firstClassId + 2,
                'month' => '1月-2月',
                'student' => json_encode([(string) $s['student_chan'], (string) $s['student_ng']]),
                'teacher' => json_encode([$wong]),
                'days' => '2025-01-05,2025-01-12,2025-01-19,2025-01-26',
                'class_year' => '2025',
                'sort' => 202501,
            ],
            [
                'class_id' => $firstClassId + 3,
                'month' => '3月-4月',
                'student' => json_encode([(string) $s['student_li'], (string) $s['student_lam'], (string) $s['student_ng']]),
                'teacher' => json_encode([$wong]),
                'days' => '2025-03-01,2025-03-08,2025-03-15,2025-03-22',
                'class_year' => '2025',
                'sort' => 202503,
            ],
            [
                'class_id' => $firstClassId + 4,
                'month' => '5月-6月',
                'student' => json_encode([(string) $s['student_chan'], (string) $s['student_li'], (string) $s['student_wong'], (string) $s['student_lam']]),
                'teacher' => json_encode([$lee, $wong]),
                'days' => '2025-05-03,2025-05-10,2025-05-17,2025-05-24',
                'class_year' => '2025',
                'sort' => 202505,
            ],
        ];

        foreach ($assignments as $a) {
            DB::table('edu_class_user')->insert(array_merge([
                'student_makeup' => null,
                'student_transfer' => null,
                'student_order' => null,
                'order_id' => null,
                'class_exam' => null,
                'history_students_status' => 0,
            ], $a));
        }
    }

    // ── Levels (3-tier tree: course → level → items) ─────────────

    private function seedLevels(): void
    {
        // Course (top-level, pid = 0)
        $courseId = DB::table('edu_level')->insertGetId([
            'pid' => 0,
            'name' => 'Swimming Assessment',
            'data' => null,
        ]);

        // Level 1 under course
        $level1Id = DB::table('edu_level')->insertGetId([
            'pid' => $courseId,
            'name' => 'Beginner',
            'data' => null,
        ]);

        // Level 2 under course
        $level2Id = DB::table('edu_level')->insertGetId([
            'pid' => $courseId,
            'name' => 'Intermediate',
            'data' => null,
        ]);

        // Items under Level 1
        DB::table('edu_level')->insert([
            ['pid' => $level1Id, 'name' => 'Freestyle 25m',    'data' => json_encode(['max_score' => 10])],
            ['pid' => $level1Id, 'name' => 'Backstroke 25m',   'data' => json_encode(['max_score' => 10])],
            ['pid' => $level1Id, 'name' => 'Water Safety',     'data' => json_encode(['max_score' => 5])],
        ]);

        // Items under Level 2
        DB::table('edu_level')->insert([
            ['pid' => $level2Id, 'name' => 'Freestyle 50m',    'data' => json_encode(['max_score' => 10])],
            ['pid' => $level2Id, 'name' => 'Breaststroke 50m', 'data' => json_encode(['max_score' => 10])],
            ['pid' => $level2Id, 'name' => 'Butterfly 25m',    'data' => json_encode(['max_score' => 10])],
        ]);
    }

    // ── BMI records ──────────────────────────────────────────────

    private function seedBmiRecords(): void
    {
        $s = $this->studentIds;

        $records = [
            // student_chan (born 2010-05-12, male) — 8 records over 3 years
            ['user_id' => $s['student_chan'], 'height' => 132.0, 'weight' => 30.0, 'hc' => 51.0, 'bmi' => round(30.0 / ((132.0 / 100) ** 2), 2), 'date' => strtotime('2023-01-15')],
            ['user_id' => $s['student_chan'], 'height' => 134.5, 'weight' => 31.5, 'hc' => 51.5, 'bmi' => round(31.5 / ((134.5 / 100) ** 2), 2), 'date' => strtotime('2023-06-15')],
            ['user_id' => $s['student_chan'], 'height' => 136.0, 'weight' => 32.5, 'hc' => 51.8, 'bmi' => round(32.5 / ((136.0 / 100) ** 2), 2), 'date' => strtotime('2024-01-15')],
            ['user_id' => $s['student_chan'], 'height' => 138.0, 'weight' => 33.5, 'hc' => 52.0, 'bmi' => round(33.5 / ((138.0 / 100) ** 2), 2), 'date' => strtotime('2024-06-15')],
            ['user_id' => $s['student_chan'], 'height' => 139.5, 'weight' => 34.5, 'hc' => 52.2, 'bmi' => round(34.5 / ((139.5 / 100) ** 2), 2), 'date' => strtotime('2024-09-15')],
            ['user_id' => $s['student_chan'], 'height' => 140.5, 'weight' => 35.2, 'hc' => 52.5, 'bmi' => round(35.2 / ((140.5 / 100) ** 2), 2), 'date' => strtotime('2025-01-15')],
            ['user_id' => $s['student_chan'], 'height' => 141.0, 'weight' => 35.5, 'hc' => 52.8, 'bmi' => round(35.5 / ((141.0 / 100) ** 2), 2), 'date' => strtotime('2025-03-15')],
            ['user_id' => $s['student_chan'], 'height' => 142.0, 'weight' => 36.0, 'hc' => 53.0, 'bmi' => round(36.0 / ((142.0 / 100) ** 2), 2), 'date' => strtotime('2025-06-15')],

            // student_li (born 2011-08-23, female) — 6 records
            ['user_id' => $s['student_li'], 'height' => 128.0, 'weight' => 26.0, 'hc' => 49.0, 'bmi' => round(26.0 / ((128.0 / 100) ** 2), 2), 'date' => strtotime('2023-03-10')],
            ['user_id' => $s['student_li'], 'height' => 130.0, 'weight' => 27.5, 'hc' => 49.5, 'bmi' => round(27.5 / ((130.0 / 100) ** 2), 2), 'date' => strtotime('2023-09-10')],
            ['user_id' => $s['student_li'], 'height' => 132.5, 'weight' => 28.5, 'hc' => 49.8, 'bmi' => round(28.5 / ((132.5 / 100) ** 2), 2), 'date' => strtotime('2024-03-10')],
            ['user_id' => $s['student_li'], 'height' => 134.0, 'weight' => 29.5, 'hc' => 50.0, 'bmi' => round(29.5 / ((134.0 / 100) ** 2), 2), 'date' => strtotime('2024-09-10')],
            ['user_id' => $s['student_li'], 'height' => 135.0, 'weight' => 30.0, 'hc' => 50.2, 'bmi' => round(30.0 / ((135.0 / 100) ** 2), 2), 'date' => strtotime('2025-02-10')],
            ['user_id' => $s['student_li'], 'height' => 136.0, 'weight' => 31.0, 'hc' => 50.5, 'bmi' => round(31.0 / ((136.0 / 100) ** 2), 2), 'date' => strtotime('2025-05-10')],

            // student_wong (born 2009-11-03, male) — 6 records
            ['user_id' => $s['student_wong'], 'height' => 142.0, 'weight' => 38.0, 'hc' => 53.5, 'bmi' => round(38.0 / ((142.0 / 100) ** 2), 2), 'date' => strtotime('2023-02-20')],
            ['user_id' => $s['student_wong'], 'height' => 145.0, 'weight' => 40.0, 'hc' => 54.0, 'bmi' => round(40.0 / ((145.0 / 100) ** 2), 2), 'date' => strtotime('2023-08-20')],
            ['user_id' => $s['student_wong'], 'height' => 147.5, 'weight' => 42.5, 'hc' => 54.5, 'bmi' => round(42.5 / ((147.5 / 100) ** 2), 2), 'date' => strtotime('2024-02-20')],
            ['user_id' => $s['student_wong'], 'height' => 149.0, 'weight' => 44.0, 'hc' => 55.0, 'bmi' => round(44.0 / ((149.0 / 100) ** 2), 2), 'date' => strtotime('2024-08-20')],
            ['user_id' => $s['student_wong'], 'height' => 150.0, 'weight' => 45.0, 'hc' => 55.2, 'bmi' => round(45.0 / ((150.0 / 100) ** 2), 2), 'date' => strtotime('2025-01-20')],
            ['user_id' => $s['student_wong'], 'height' => 151.0, 'weight' => 46.0, 'hc' => 55.5, 'bmi' => round(46.0 / ((151.0 / 100) ** 2), 2), 'date' => strtotime('2025-04-20')],

            // student_lam (born 2012-02-17, female) — 4 records
            ['user_id' => $s['student_lam'], 'height' => 125.0, 'weight' => 25.0, 'hc' => 49.0, 'bmi' => round(25.0 / ((125.0 / 100) ** 2), 2), 'date' => strtotime('2024-01-05')],
            ['user_id' => $s['student_lam'], 'height' => 127.0, 'weight' => 26.0, 'hc' => 49.3, 'bmi' => round(26.0 / ((127.0 / 100) ** 2), 2), 'date' => strtotime('2024-07-05')],
            ['user_id' => $s['student_lam'], 'height' => 128.5, 'weight' => 27.0, 'hc' => 49.5, 'bmi' => round(27.0 / ((128.5 / 100) ** 2), 2), 'date' => strtotime('2025-01-05')],
            ['user_id' => $s['student_lam'], 'height' => 130.0, 'weight' => 28.0, 'hc' => 49.8, 'bmi' => round(28.0 / ((130.0 / 100) ** 2), 2), 'date' => strtotime('2025-05-05')],

            // student_ng (born 2008-07-30, male) — 4 records
            ['user_id' => $s['student_ng'], 'height' => 155.0, 'weight' => 48.0, 'hc' => 56.0, 'bmi' => round(48.0 / ((155.0 / 100) ** 2), 2), 'date' => strtotime('2024-01-10')],
            ['user_id' => $s['student_ng'], 'height' => 157.0, 'weight' => 50.0, 'hc' => 56.2, 'bmi' => round(50.0 / ((157.0 / 100) ** 2), 2), 'date' => strtotime('2024-07-10')],
            ['user_id' => $s['student_ng'], 'height' => 158.5, 'weight' => 51.0, 'hc' => 56.5, 'bmi' => round(51.0 / ((158.5 / 100) ** 2), 2), 'date' => strtotime('2025-01-10')],
            ['user_id' => $s['student_ng'], 'height' => 160.0, 'weight' => 52.0, 'hc' => 56.8, 'bmi' => round(52.0 / ((160.0 / 100) ** 2), 2), 'date' => strtotime('2025-06-10')],
        ];

        foreach ($records as $r) {
            DB::table('edu_bmi')->insert($r);
        }
    }

    // ── Results ──────────────────────────────────────────────────

    private function seedResults(): void
    {
        $s = $this->studentIds;
        $firstClassId = DB::table('edu_class')->min('class_id');

        // Get level item IDs (leaf nodes)
        $levelItems = DB::table('edu_level')->where('pid', '>', 0)
            ->whereRaw('id NOT IN (SELECT DISTINCT pid FROM wp_3x_edu_level WHERE pid > 0)')
            ->pluck('id')
            ->toArray();

        $results = [
            // ── 2025 results ──────────────────────────────────────

            // student_chan in class 1 (Coach Lee, 1月-2月)
            ['class_id' => $firstClassId, 'class_month' => '1月-2月', 'exam_id' => $levelItems[0] ?? 1, 'user_id' => $s['student_chan'], 'first_name' => 'Tai Man', 'last_name' => 'Chan', 'exam_type' => 'score', 'exam_name' => 'Freestyle 25m', 'exam_data' => '8', 'exam_date' => '2025-01-25', 'class_year' => '2025', 'created' => strtotime('2025-01-25')],
            ['class_id' => $firstClassId, 'class_month' => '1月-2月', 'exam_id' => $levelItems[1] ?? 2, 'user_id' => $s['student_chan'], 'first_name' => 'Tai Man', 'last_name' => 'Chan', 'exam_type' => 'score', 'exam_name' => 'Backstroke 25m', 'exam_data' => '7', 'exam_date' => '2025-01-25', 'class_year' => '2025', 'created' => strtotime('2025-01-25')],

            // student_li in class 1 (Coach Lee, 1月-2月)
            ['class_id' => $firstClassId, 'class_month' => '1月-2月', 'exam_id' => $levelItems[0] ?? 1, 'user_id' => $s['student_li'], 'first_name' => 'Ka Yan', 'last_name' => 'Li', 'exam_type' => 'score', 'exam_name' => 'Freestyle 25m', 'exam_data' => '9', 'exam_date' => '2025-01-25', 'class_year' => '2025', 'created' => strtotime('2025-01-25')],
            ['class_id' => $firstClassId, 'class_month' => '1月-2月', 'exam_id' => $levelItems[2] ?? 3, 'user_id' => $s['student_li'], 'first_name' => 'Ka Yan', 'last_name' => 'Li', 'exam_type' => 'score', 'exam_name' => 'Water Safety', 'exam_data' => '4', 'exam_date' => '2025-02-01', 'class_year' => '2025', 'created' => strtotime('2025-02-01')],

            // student_wong in class 2 (Coach Lee, 1月-2月)
            ['class_id' => $firstClassId + 1, 'class_month' => '1月-2月', 'exam_id' => $levelItems[3] ?? 4, 'user_id' => $s['student_wong'], 'first_name' => 'Siu Ming', 'last_name' => 'Wong', 'exam_type' => 'score', 'exam_name' => 'Freestyle 50m', 'exam_data' => '6', 'exam_date' => '2025-01-25', 'class_year' => '2025', 'created' => strtotime('2025-01-25')],
            ['class_id' => $firstClassId + 1, 'class_month' => '1月-2月', 'exam_id' => $levelItems[4] ?? 5, 'user_id' => $s['student_wong'], 'first_name' => 'Siu Ming', 'last_name' => 'Wong', 'exam_type' => 'score', 'exam_name' => 'Breaststroke 50m', 'exam_data' => '5', 'exam_date' => '2025-02-08', 'class_year' => '2025', 'created' => strtotime('2025-02-08')],

            // student_lam in class 2 (Coach Lee, 1月-2月)
            ['class_id' => $firstClassId + 1, 'class_month' => '1月-2月', 'exam_id' => $levelItems[3] ?? 4, 'user_id' => $s['student_lam'], 'first_name' => 'Hoi Yin', 'last_name' => 'Lam', 'exam_type' => 'score', 'exam_name' => 'Freestyle 50m', 'exam_data' => '7', 'exam_date' => '2025-01-25', 'class_year' => '2025', 'created' => strtotime('2025-01-25')],

            // student_ng in class 3 (Coach Wong, 1月-2月)
            ['class_id' => $firstClassId + 2, 'class_month' => '1月-2月', 'exam_id' => $levelItems[0] ?? 1, 'user_id' => $s['student_ng'], 'first_name' => 'Chi Wai', 'last_name' => 'Ng', 'exam_type' => 'score', 'exam_name' => 'Freestyle 25m', 'exam_data' => '9', 'exam_date' => '2025-01-26', 'class_year' => '2025', 'created' => strtotime('2025-01-26')],
            ['class_id' => $firstClassId + 2, 'class_month' => '1月-2月', 'exam_id' => $levelItems[2] ?? 3, 'user_id' => $s['student_ng'], 'first_name' => 'Chi Wai', 'last_name' => 'Ng', 'exam_type' => 'score', 'exam_name' => 'Water Safety', 'exam_data' => '5', 'exam_date' => '2025-01-26', 'class_year' => '2025', 'created' => strtotime('2025-01-26')],

            // student_chan in class 3 (Coach Wong, 1月-2月)
            ['class_id' => $firstClassId + 2, 'class_month' => '1月-2月', 'exam_id' => $levelItems[0] ?? 1, 'user_id' => $s['student_chan'], 'first_name' => 'Tai Man', 'last_name' => 'Chan', 'exam_type' => 'score', 'exam_name' => 'Freestyle 25m', 'exam_data' => '8', 'exam_date' => '2025-01-26', 'class_year' => '2025', 'created' => strtotime('2025-01-26')],

            // Coach Wong's class 4 (3月-4月)
            ['class_id' => $firstClassId + 3, 'class_month' => '3月-4月', 'exam_id' => $levelItems[5] ?? 6, 'user_id' => $s['student_li'], 'first_name' => 'Ka Yan', 'last_name' => 'Li', 'exam_type' => 'score', 'exam_name' => 'Butterfly 25m', 'exam_data' => '6', 'exam_date' => '2025-03-22', 'class_year' => '2025', 'created' => strtotime('2025-03-22')],
            ['class_id' => $firstClassId + 3, 'class_month' => '3月-4月', 'exam_id' => $levelItems[5] ?? 6, 'user_id' => $s['student_ng'], 'first_name' => 'Chi Wai', 'last_name' => 'Ng', 'exam_type' => 'score', 'exam_name' => 'Butterfly 25m', 'exam_data' => '8', 'exam_date' => '2025-03-22', 'class_year' => '2025', 'created' => strtotime('2025-03-22')],
            ['class_id' => $firstClassId + 3, 'class_month' => '3月-4月', 'exam_id' => $levelItems[5] ?? 6, 'user_id' => $s['student_lam'], 'first_name' => 'Hoi Yin', 'last_name' => 'Lam', 'exam_type' => 'score', 'exam_name' => 'Butterfly 25m', 'exam_data' => '7', 'exam_date' => '2025-03-22', 'class_year' => '2025', 'created' => strtotime('2025-03-22')],

            // ── 2024 results (history) ────────────────────────────

            // student_chan in 2024 class 8 (Coach Lee)
            ['class_id' => $firstClassId + 7, 'class_month' => '7月-8月', 'exam_id' => $levelItems[0] ?? 1, 'user_id' => $s['student_chan'], 'first_name' => 'Tai Man', 'last_name' => 'Chan', 'exam_type' => 'score', 'exam_name' => 'Freestyle 25m', 'exam_data' => '6', 'exam_date' => '2024-07-27', 'class_year' => '2024', 'created' => strtotime('2024-07-27')],
            ['class_id' => $firstClassId + 7, 'class_month' => '7月-8月', 'exam_id' => $levelItems[1] ?? 2, 'user_id' => $s['student_chan'], 'first_name' => 'Tai Man', 'last_name' => 'Chan', 'exam_type' => 'score', 'exam_name' => 'Backstroke 25m', 'exam_data' => '5', 'exam_date' => '2024-07-27', 'class_year' => '2024', 'created' => strtotime('2024-07-27')],
            ['class_id' => $firstClassId + 7, 'class_month' => '9月-10月', 'exam_id' => $levelItems[0] ?? 1, 'user_id' => $s['student_chan'], 'first_name' => 'Tai Man', 'last_name' => 'Chan', 'exam_type' => 'score', 'exam_name' => 'Freestyle 25m', 'exam_data' => '7', 'exam_date' => '2024-09-21', 'class_year' => '2024', 'created' => strtotime('2024-09-21')],

            // student_li in 2024 class 8 (Coach Lee)
            ['class_id' => $firstClassId + 7, 'class_month' => '7月-8月', 'exam_id' => $levelItems[0] ?? 1, 'user_id' => $s['student_li'], 'first_name' => 'Ka Yan', 'last_name' => 'Li', 'exam_type' => 'score', 'exam_name' => 'Freestyle 25m', 'exam_data' => '7', 'exam_date' => '2024-07-27', 'class_year' => '2024', 'created' => strtotime('2024-07-27')],

            // student_wong in 2024 class 9 (Coach Wong)
            ['class_id' => $firstClassId + 8, 'class_month' => '9月-10月', 'exam_id' => $levelItems[3] ?? 4, 'user_id' => $s['student_wong'], 'first_name' => 'Siu Ming', 'last_name' => 'Wong', 'exam_type' => 'score', 'exam_name' => 'Freestyle 50m', 'exam_data' => '5', 'exam_date' => '2024-09-22', 'class_year' => '2024', 'created' => strtotime('2024-09-22')],
            ['class_id' => $firstClassId + 8, 'class_month' => '11月-12月', 'exam_id' => $levelItems[4] ?? 5, 'user_id' => $s['student_wong'], 'first_name' => 'Siu Ming', 'last_name' => 'Wong', 'exam_type' => 'score', 'exam_name' => 'Breaststroke 50m', 'exam_data' => '4', 'exam_date' => '2024-11-17', 'class_year' => '2024', 'created' => strtotime('2024-11-17')],

            // student_ng in 2024 class 10 (Coach Wong)
            ['class_id' => $firstClassId + 9, 'class_month' => '11月-12月', 'exam_id' => $levelItems[5] ?? 6, 'user_id' => $s['student_ng'], 'first_name' => 'Chi Wai', 'last_name' => 'Ng', 'exam_type' => 'score', 'exam_name' => 'Butterfly 25m', 'exam_data' => '7', 'exam_date' => '2024-11-23', 'class_year' => '2024', 'created' => strtotime('2024-11-23')],
        ];

        $defaults = [
            'gender' => '',
            'birthdate' => '',
            'exam_lap_times' => null,
            'exam_fastest_lap_sec' => 0,
            'exam_slowest_lap_sec' => 0,
            'exam_avg_lap_sec' => 0,
            'exam_history' => null,
            'exam_note' => '',
            'status' => 1,
        ];

        foreach ($results as $r) {
            DB::table('edu_result')->insert(array_merge($defaults, $r));
        }
    }

    // ── 2024 Class–User assignments (for history data) ──────────

    private function seed2024ClassUsers(): void
    {
        $lee = (string) $this->coachLeeId;
        $wong = (string) $this->coachWongId;
        $s = $this->studentIds;

        $firstClassId = DB::table('edu_class')->min('class_id');

        $assignments = [
            // 2024 class 8: Coach Lee — chan + li
            [
                'class_id' => $firstClassId + 7,
                'month' => '7月-8月',
                'student' => json_encode([(string) $s['student_chan'], (string) $s['student_li']]),
                'teacher' => json_encode([$lee]),
                'class_year' => '2024',
                'sort' => 202407,
            ],
            // 2024 class 9: Coach Wong — wong
            [
                'class_id' => $firstClassId + 8,
                'month' => '9月-10月',
                'student' => json_encode([(string) $s['student_wong']]),
                'teacher' => json_encode([$wong]),
                'class_year' => '2024',
                'sort' => 202409,
            ],
            // 2024 class 10: Coach Wong — ng
            [
                'class_id' => $firstClassId + 9,
                'month' => '11月-12月',
                'student' => json_encode([(string) $s['student_ng']]),
                'teacher' => json_encode([$wong]),
                'class_year' => '2024',
                'sort' => 202411,
            ],
        ];

        foreach ($assignments as $a) {
            DB::table('edu_class_user')->insert(array_merge([
                'days' => '',
                'student_makeup' => null,
                'student_transfer' => null,
                'student_order' => null,
                'order_id' => null,
                'class_exam' => null,
                'history_students_status' => 0,
            ], $a));
        }
    }
}
