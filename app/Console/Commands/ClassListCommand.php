<?php

namespace App\Console\Commands;

use App\Services\ClassQueryService;
use Illuminate\Console\Command;

class ClassListCommand extends Command
{
    protected $signature = 'class:list {year} {--district=}';

    protected $description = 'List edu classes by year and optional district ID';

    public function handle(ClassQueryService $service): int
    {
        $filters = ['class_year' => $this->argument('year')];

        if ($district = $this->option('district')) {
            $filters['district_id'] = (int) $district;
        }

        $classes = $service->getClasses($filters);

        if ($classes->isEmpty()) {
            $this->warn('No classes found.');

            return self::SUCCESS;
        }

        $this->info("Found {$classes->count()} class(es):");

        $this->table(
            ['ID', 'Name', 'District', 'Year', 'Schedule', 'Months'],
            $classes->map(fn ($c) => [
                $c->class_id,
                mb_strimwidth($c->class_name ?? '', 0, 30, '...'),
                $c->district_id,
                $c->class_year,
                mb_strimwidth($c->date_time ?? '', 0, 25, '...'),
                $c->date_month ? implode(', ', $c->date_month) : '-',
            ])
        );

        return self::SUCCESS;
    }
}
