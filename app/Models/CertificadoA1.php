<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CertificadoA1 extends Model
{
    use SoftDeletes;

    protected $table = 'certificados_a1';

    public const CREATED_AT = 'criado_em';
    public const UPDATED_AT = 'alterado_em';
    public const DELETED_AT = 'apagado_em';

    protected $fillable = ['nome'];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'criado_em' => 'datetime',
            'alterado_em' => 'datetime',
            'apagado_em' => 'datetime',
        ];
    }
}
