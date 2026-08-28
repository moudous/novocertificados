<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Variavel extends Model
{
    use SoftDeletes;

    protected $table = 'variaveis';

    public const CREATED_AT = 'criado_em';
    public const UPDATED_AT = 'alterado_em';
    public const DELETED_AT = 'apagado_em';

    protected $fillable = [
        'tipo', 'imagem', 'texto', 'ativo', 'pos_x', 'pox_y', 'altura', 'largura',
        'alinhamento', 'cor', 'centro_x', 'centro_y',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'ativo' => 'boolean',
            'pos_x' => 'integer',
            'pox_y' => 'integer',
            'altura' => 'integer',
            'largura' => 'integer',
            'centro_x' => 'integer',
            'centro_y' => 'integer',
            'criado_em' => 'datetime',
            'alterado_em' => 'datetime',
            'apagado_em' => 'datetime',
        ];
    }

    public function imageExists(): bool
    {
        return filled($this->imagem)
            && basename((string) $this->imagem) === $this->imagem
            && is_file(public_path('certificado/imagem_fundo/'.$this->imagem));
    }

    public function imageUrl(): ?string
    {
        return $this->imageExists()
            ? asset('certificado/imagem_fundo/'.$this->imagem)
            : null;
    }
}
