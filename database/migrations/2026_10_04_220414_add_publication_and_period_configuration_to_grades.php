<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('subject_coefficient');
            $table->foreignId('published_by')->nullable()->after('published_at')->constrained('users')->restrictOnDelete();
        });

        Schema::create('school_year_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_year_id')->constrained()->restrictOnDelete();
            $table->string('period_type', 12);
            $table->unsignedTinyInteger('period_number');
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();

            $table->unique(['school_year_id', 'period_type', 'period_number']);
            $table->index(['school_year_id', 'start_date', 'end_date']);
        });

        DB::statement("ALTER TABLE school_year_periods ADD CONSTRAINT school_year_periods_type_check CHECK (period_type IN ('trimester', 'semester', 'annual'))");
        DB::statement('ALTER TABLE school_year_periods ADD CONSTRAINT school_year_periods_dates_check CHECK (end_date >= start_date)');

        foreach (DB::table('school_years')->orderBy('id')->get() as $schoolYear) {
            $start = CarbonImmutable::parse($schoolYear->start_date);
            $end = CarbonImmutable::parse($schoolYear->end_date);
            $days = $start->diffInDays($end) + 1;

            foreach ([
                'trimester' => ['label' => 'Trimestre', 'count' => 3],
                'semester' => ['label' => 'Semestre', 'count' => 2],
                'annual' => ['label' => 'Année complète', 'count' => 1],
            ] as $type => $definition) {
                for ($number = 1; $number <= $definition['count']; $number++) {
                    $startOffset = intdiv($days * ($number - 1), $definition['count']);
                    $endOffset = intdiv($days * $number - 1, $definition['count']);

                    DB::table('school_year_periods')->insert([
                        'school_year_id' => $schoolYear->id,
                        'period_type' => $type,
                        'period_number' => $number,
                        'start_date' => $start->addDays($startOffset)->toDateString(),
                        'end_date' => $start->addDays($endOffset)->toDateString(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        Schema::create('grade_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_id')->constrained()->restrictOnDelete();
            $table->foreignId('corrected_by')->constrained('users')->restrictOnDelete();
            $table->decimal('previous_score', 5, 2)->nullable();
            $table->decimal('new_score', 5, 2);
            $table->text('reason');
            $table->timestamps();

            $table->index(['grade_id', 'created_at']);
        });

        DB::statement('ALTER TABLE grade_corrections ADD CONSTRAINT grade_corrections_scores_check CHECK (new_score BETWEEN 0 AND 20 AND (previous_score IS NULL OR previous_score BETWEEN 0 AND 20))');

        // Existing scored results were already visible in reports before publication became explicit.
        DB::statement('
            UPDATE evaluations
            SET published_at = COALESCE(
                    (SELECT MAX(grades.updated_at) FROM grades WHERE grades.evaluation_id = evaluations.id AND grades.score IS NOT NULL),
                    evaluations.updated_at,
                    CURRENT_TIMESTAMP
                ),
                published_by = evaluations.created_by
            WHERE EXISTS (
                SELECT 1 FROM grades WHERE grades.evaluation_id = evaluations.id AND grades.score IS NOT NULL
            )
        ');
    }

    public function down(): void
    {
        if (DB::table('grade_corrections')->exists()
            || DB::table('school_year_periods')->exists()
            || DB::table('evaluations')->whereNotNull('published_at')->exists()) {
            throw new RuntimeException('Published grades, correction audits, or configured periods cannot be rolled back without losing academic history.');
        }

        Schema::dropIfExists('grade_corrections');
        Schema::dropIfExists('school_year_periods');
        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('published_by');
            $table->dropColumn('published_at');
        });
    }
};
