<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pessoa extends Model
{
    protected $table = 'pessoas';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = ['id', 'usuario', 'nome', 'email', 'perfil', 'perfil_id', 'ativo'];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'perfil_id' => 'integer',
            'ativo' => 'boolean',
        ];
    }
}
