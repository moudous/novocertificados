<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Certificado extends Model
{
    use SoftDeletes;

    protected $table = 'certificados';

    public const CREATED_AT = 'criado_em';
    public const UPDATED_AT = 'atualizado_em';
    public const DELETED_AT = 'apagado_em';

    protected $fillable = [
        'participanteId', 'nome', 'arquivo', 'atividadeId', 'titulo', 'titulo2',
        'titulo3', 'titulo4', 'cargaHoraria', 'outrosParticipantes', 'tipo', 'ativo', 'arquivo_old',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'participanteId' => 'integer',
            'atividadeId' => 'integer',
            'cargaHoraria' => 'integer',
            'ativo' => 'boolean',
            'criado_em' => 'datetime',
            'atualizado_em' => 'datetime',
            'apagado_em' => 'datetime',
        ];
    }

    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class, 'participanteId')->withTrashed();
    }

    public function atividade(): BelongsTo
    {
        return $this->belongsTo(Atividade::class, 'atividadeId')->withTrashed();
    }
}
