<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Evento extends Model
{
    use SoftDeletes;

    protected $table = 'eventos';

    public const CREATED_AT = 'criado_em';
    public const UPDATED_AT = 'atualizado_em';
    public const DELETED_AT = 'apagado_em';

    protected $fillable = ['nome', 'periodos', 'ativo', 'descricao'];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'ativo' => 'boolean',
            'criado_em' => 'datetime',
            'atualizado_em' => 'datetime',
            'apagado_em' => 'datetime',
        ];
    }
}
