<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class Pessoa extends Model
{
    protected $table = 'pessoas';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = ['id', 'usuario', 'nome', 'email', 'perfil', 'perfil_id', 'perfis', 'ativo', 'ultimo_acesso'];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'perfil_id' => 'integer',
            'perfis' => 'array',
            'ativo' => 'boolean',
            'ultimo_acesso' => 'datetime',
        ];
    }

    public function ultimaSincronizacaoLocal(): ?CarbonInterface
    {
        $updatedAt = $this->getAttributes()['updated_at'] ?? null;

        return $updatedAt
            ? CarbonImmutable::parse($updatedAt, 'UTC')->setTimezone(config('app.display_timezone'))
            : null;
    }
}
