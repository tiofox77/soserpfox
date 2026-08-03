<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $groups = DB::table('agt_submissions')
            ->select('tenant_id', 'document_type', 'document_id', DB::raw('COUNT(*) as total'))
            ->groupBy('tenant_id', 'document_type', 'document_id')
            ->having('total', '>', 1)
            ->get();

        foreach ($groups as $group) {
            $rows = DB::table('agt_submissions')
                ->where('tenant_id', $group->tenant_id)
                ->where('document_type', $group->document_type)
                ->where('document_id', $group->document_id)
                ->get();

            $keep = $rows->sortBy(function ($row) {
                $priority = match ($row->status) {
                    'validated' => 1,
                    'submitted' => 2,
                    'pending' => 3,
                    default => 4,
                };
                return sprintf('%d-%010d', $priority, 9999999999 - (int) $row->id);
            })->first();

            DB::table('agt_communication_logs')
                ->whereIn('submission_id', $rows->where('id', '<>', $keep->id)->pluck('id'))
                ->update(['submission_id' => $keep->id]);

            DB::table('agt_submissions')
                ->whereIn('id', $rows->where('id', '<>', $keep->id)->pluck('id'))
                ->delete();
        }

        // Uma falha de rede posterior podia deixar o documento como rejeitado,
        // mesmo quando uma tentativa anterior já tinha sido validada pela AGT.
        DB::table('agt_submissions')
            ->where('status', 'validated')
            ->orderBy('id')
            ->chunkById(100, function ($submissions): void {
                foreach ($submissions as $submission) {
                    if (!class_exists($submission->document_type)) {
                        continue;
                    }

                    $model = new $submission->document_type();
                    $table = $model->getTable();
                    $columns = \Illuminate\Support\Facades\Schema::getColumnListing($table);
                    $values = array_intersect_key([
                        'agt_status' => 'validated',
                        'agt_reference' => $submission->agt_reference,
                        'agt_validated_at' => $submission->validated_at,
                        'updated_at' => now(),
                    ], array_flip($columns));

                    if ($values !== []) {
                        DB::table($table)
                            ->where($model->getKeyName(), $submission->document_id)
                            ->where('tenant_id', $submission->tenant_id)
                            ->update($values);
                    }
                }
            });
    }

    public function down(): void
    {
        // Consolidação de auditoria não é reversível.
    }
};
