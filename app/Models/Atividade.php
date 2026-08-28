<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Atividade extends Model
{
    use SoftDeletes;

    protected $table = 'atividades';

    public const CREATED_AT = 'criado_em';
    public const UPDATED_AT = 'atualizado_em';
    public const DELETED_AT = 'apagado_em';

    protected $fillable = ['eventoId', 'nome', 'descricao_old', 'periodos', 'ativo', 'imagemFundo', 'template'];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'eventoId' => 'integer',
            'ativo' => 'boolean',
            'criado_em' => 'datetime',
            'atualizado_em' => 'datetime',
            'apagado_em' => 'datetime',
        ];
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'eventoId');
    }
}
