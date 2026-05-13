<?php

namespace Database\Factories;

use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    private static int $sequence = 0;

    public function definition(): array
    {
        self::$sequence++;
        return [
            'region_code'            => 1703,
            'task_number'            => 'T' . str_pad((string) self::$sequence, 4, '0', STR_PAD_LEFT),
            'title'                  => fake()->sentence(6),
            'executor_text'          => fake()->company(),
            'kind'                   => 'kpi',
            'module_code'            => 'macro',
            'indicator_code'         => 'grp',
            'section_path'           => 'I',
            'section_label'          => 'I боб',
            'source_paragraph_index' => self::$sequence,
            'status'                 => 'open',
        ];
    }
}
