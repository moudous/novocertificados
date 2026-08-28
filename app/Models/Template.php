<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Template extends Model
{
    use SoftDeletes;

    protected $table = 'templates';

    public const CREATED_AT = 'crido_em';
    public const UPDATED_AT = 'alterado_em';
    public const DELETED_AT = 'apagado_em';

    protected $fillable = ['nome', 'fundo', 'ativo', 'certificado_a1', 'largura', 'altura', 'pagina', 'layout_pagina', 'elementos_layout'];

    protected function casts(): array
    {
        return [
            'id' => 'integer', 'ativo' => 'boolean', 'certificado_a1' => 'integer',
            'largura' => 'integer', 'altura' => 'integer', 'crido_em' => 'datetime',
            'alterado_em' => 'datetime', 'apagado_em' => 'datetime', 'elementos_layout' => 'array',
        ];
    }

    public function certificadoA1(): BelongsTo
    {
        return $this->belongsTo(CertificadoA1::class, 'certificado_a1')->withTrashed();
    }

    public function backgroundExists(): bool
    {
        return filled($this->fundo) && basename((string) $this->fundo) === $this->fundo
            && is_file(public_path('certificado/imagem_fundo/'.$this->fundo));
    }

    public function backgroundUrl(): ?string
    {
        return $this->backgroundExists() ? asset('certificado/imagem_fundo/'.$this->fundo) : null;
    }
}
