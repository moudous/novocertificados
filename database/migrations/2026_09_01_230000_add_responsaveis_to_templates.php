<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table): void {
            $table->text('responsaveis')->nullable()->after('campos_dinamicos');
        });

        $responsaveisPorParticipante = DB::table('responsaveis')->pluck('id', 'participante_id');
        $participantesPorRubrica = DB::table('rubricas_participantes')->pluck('participante_id', 'id');

        DB::table('templates')->select(['id', 'elementos_layout'])->orderBy('id')->chunkById(200, function ($templates) use ($responsaveisPorParticipante, $participantesPorRubrica): void {
            foreach ($templates as $template) {
                $layout = json_decode((string) $template->elementos_layout, true);
                $ids = collect(is_array($layout) ? $layout : [])->filter(fn (array $element): bool => ($element['source_type'] ?? '') === 'responsible_signature')
                    ->pluck('rubrica_id')->filter()->map(fn ($rubricaId) => $participantesPorRubrica->get((int) $rubricaId))
                    ->filter()->map(fn ($participanteId) => $responsaveisPorParticipante->get((int) $participanteId))
                    ->filter()->map(fn ($id): int => (int) $id)->unique()->sort()->values();
                DB::table('templates')->where('id', $template->id)->update(['responsaveis' => $ids->isEmpty() ? null : $ids->implode(',')]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table): void {
            $table->dropColumn('responsaveis');
        });
    }
};
