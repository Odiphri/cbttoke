<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->modifyColumns([
            'lesson_notes' => [
                'previous_knowledge',
                'learning_objectives',
                'teaching_materials',
                'introduction',
                'evaluation',
                'conclusion',
                'assignment',
            ],
            'lesson_note_reviews' => ['comments'],
            'lesson_exercises' => ['instructions'],
            'exercise_questions' => ['marking_guide'],
            'exercise_attempts' => ['overall_feedback'],
            'exercise_answers' => ['answer_text', 'teacher_feedback'],
            'exercise_retry_grants' => ['reason'],
        ], 'LONGTEXT NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->modifyColumns([
            'lesson_notes' => [
                'previous_knowledge',
                'learning_objectives',
                'teaching_materials',
                'introduction',
                'evaluation',
                'conclusion',
                'assignment',
            ],
            'lesson_note_reviews' => ['comments'],
            'lesson_exercises' => ['instructions'],
            'exercise_questions' => ['marking_guide'],
            'exercise_attempts' => ['overall_feedback'],
            'exercise_answers' => ['answer_text', 'teacher_feedback'],
            'exercise_retry_grants' => ['reason'],
        ], 'TEXT NULL');
    }

    private function modifyColumns(array $tables, string $definition): void
    {
        foreach ($tables as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$definition}");
                }
            }
        }
    }
};
