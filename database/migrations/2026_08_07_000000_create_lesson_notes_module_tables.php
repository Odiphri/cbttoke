<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('week_number');
            $table->string('title');
            $table->string('topic');
            $table->string('subtopic')->nullable();
            $table->date('lesson_date')->nullable();
            $table->longText('previous_knowledge')->nullable();
            $table->longText('learning_objectives')->nullable();
            $table->longText('teaching_materials')->nullable();
            $table->longText('introduction')->nullable();
            $table->longText('main_content');
            $table->longText('evaluation')->nullable();
            $table->longText('conclusion')->nullable();
            $table->longText('assignment')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['academic_session_id', 'week_number', 'school_class_id', 'subject_id', 'teacher_id', 'status'],
                'lesson_notes_duplicate_guard'
            );
        });

        Schema::create('lesson_note_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->string('action');
            $table->longText('comments')->nullable();
            $table->timestamp('reviewed_at');
            $table->timestamps();
        });

        Schema::create('lesson_note_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_note_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->timestamps();
        });

        Schema::create('lesson_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_note_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('instructions')->nullable();
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->boolean('allow_late_submission')->default(false);
            $table->boolean('shuffle_questions')->default(false);
            $table->boolean('shuffle_options')->default(false);
            $table->boolean('show_score_immediately')->default(true);
            $table->boolean('reveal_correct_answers')->default(false);
            $table->string('attempt_mode')->default('one');
            $table->unsignedInteger('max_attempts')->nullable();
            $table->string('score_selection_method')->default('highest');
            $table->timestamps();
        });

        Schema::create('exercise_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_exercise_id')->constrained()->cascadeOnDelete();
            $table->string('question_type');
            $table->longText('question_text');
            $table->json('options')->nullable();
            $table->string('correct_answer')->nullable();
            $table->longText('marking_guide')->nullable();
            $table->decimal('marks', 8, 2)->default(1);
            $table->string('image_path')->nullable();
            $table->unsignedInteger('display_order')->default(1);
            $table->timestamps();
        });

        Schema::create('exercise_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_exercise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('status')->default('in_progress')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('marked_at')->nullable();
            $table->decimal('auto_score', 8, 2)->default(0);
            $table->decimal('manual_score', 8, 2)->default(0);
            $table->decimal('total_score', 8, 2)->default(0);
            $table->longText('overall_feedback')->nullable();
            $table->boolean('is_counted')->default(false);
            $table->timestamps();

            $table->unique(['lesson_exercise_id', 'student_id', 'attempt_number'], 'exercise_attempt_number_unique');
        });

        Schema::create('exercise_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_question_id')->constrained()->cascadeOnDelete();
            $table->longText('answer_text')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('awarded_marks', 8, 2)->nullable();
            $table->longText('teacher_feedback')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('marked_at')->nullable();
            $table->timestamps();

            $table->unique(['exercise_attempt_id', 'exercise_question_id'], 'exercise_answer_unique');
        });

        Schema::create('exercise_retry_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_exercise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('granted_by')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('extra_attempts')->default(1);
            $table->longText('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_retry_grants');
        Schema::dropIfExists('exercise_answers');
        Schema::dropIfExists('exercise_attempts');
        Schema::dropIfExists('exercise_questions');
        Schema::dropIfExists('lesson_exercises');
        Schema::dropIfExists('lesson_note_attachments');
        Schema::dropIfExists('lesson_note_reviews');
        Schema::dropIfExists('lesson_notes');
    }
};
