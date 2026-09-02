<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('novos_certificados', function (Blueprint $table): void {
            $table->string('carga_horaria', 50)->nullable()->after('data_emissao');
        });

        DB::table('novos_certificados')->whereNotNull('campos_personalizados')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $custom = json_decode((string) $row->campos_personalizados, true);
                $hours = is_array($custom) ? trim((string) ($custom['carga_horaria'] ?? '')) : '';
                if ($hours !== '') DB::table('novos_certificados')->where('id', $row->id)->update(['carga_horaria' => mb_substr($hours, 0, 50)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('novos_certificados', function (Blueprint $table): void {
            $table->dropColumn('carga_horaria');
        });
    }
};
