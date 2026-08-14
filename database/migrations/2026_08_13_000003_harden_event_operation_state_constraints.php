<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, array{column: string, values: list<string>}> */
    private const CONSTRAINTS = [
        'tournaments' => ['column' => 'format', 'values' => ['single_elimination', 'double_elimination', 'round_robin']],
        'discipline_placements' => ['column' => 'state', 'values' => ['submitted', 'approved', 'voided']],
        'discipline_entries' => ['column' => 'state', 'values' => ['draft', 'locked']],
        'bracket_nodes' => ['column' => 'state', 'values' => ['pending', 'resolved', 'bye_resolved', 'skipped']],
        'advancement_rules' => ['column' => 'outcome', 'values' => ['winner', 'loser']],
        'bracket_slots' => ['column' => 'source_result', 'values' => ['winner', 'loser', 'reset_participant']],
    ];

    public function up(): void
    {
        foreach (self::CONSTRAINTS as $table => $definition) {
            $invalid = DB::table($table)
                ->whereNotNull($definition['column'])
                ->whereNotIn($definition['column'], $definition['values'])
                ->distinct()
                ->pluck($definition['column'])
                ->map(static fn ($value): string => (string) $value)
                ->all();

            if ($invalid !== []) {
                throw new RuntimeException(sprintf(
                    'Cannot add the %s state constraint: %s.%s contains unsupported values [%s]. Review or migrate those rows before retrying.',
                    $table,
                    $table,
                    $definition['column'],
                    implode(', ', $invalid),
                ));
            }
        }

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::CONSTRAINTS as $table => $definition) {
            $constraint = $table.'_'.$definition['column'].'_bounded_check';
            $exists = DB::table('pg_constraint')
                ->where('conname', $constraint)
                ->exists();
            if ($exists) {
                continue;
            }

            $values = implode(', ', array_map(
                static fn (string $value): string => "'".str_replace("'", "''", $value)."'",
                $definition['values'],
            ));
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK ({$definition['column']} IN ({$values}))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::CONSTRAINTS as $table => $definition) {
            DB::statement('ALTER TABLE '.$table.' DROP CONSTRAINT IF EXISTS '.$table.'_'.$definition['column'].'_bounded_check');
        }
    }
};
