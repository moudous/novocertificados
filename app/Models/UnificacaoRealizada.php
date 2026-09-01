<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnificacaoRealizada extends Model
{
    protected $table = 'unificacoes_realizadas';
    public const CREATED_AT = 'criado_em';
    public const UPDATED_AT = 'alterado_em';

    protected $fillable = [
        'participante_novo_id', 'participante_novo_nome', 'participantes_excluidos',
        'usuario_id', 'usuario_nome', 'dados_antes', 'dados_depois', 'status',
        'desfeito_por', 'desfeito_em',
    ];

    protected function casts(): array
    {
        return [
            'participante_novo_id' => 'integer',
            'participantes_excluidos' => 'array',
            'dados_antes' => 'array',
            'dados_depois' => 'array',
            'usuario_id' => 'integer',
            'desfeito_por' => 'integer',
            'desfeito_em' => 'datetime',
            'criado_em' => 'datetime',
            'alterado_em' => 'datetime',
        ];
    }
}
