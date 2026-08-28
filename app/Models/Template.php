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

    protected $fillable = ['nome', 'fundo', 'fundo_colorido', 'cor_fundo', 'fundo_colorido_ativo', 'tipo_fundo', 'fundo_degrade', 'cor_degrade_inicio', 'cor_degrade_fim', 'direcao_degrade', 'ativo', 'certificado_a1', 'largura', 'altura', 'pagina', 'layout_pagina', 'elementos_layout'];

    protected function casts(): array
    {
        return [
            'id' => 'integer', 'ativo' => 'boolean', 'fundo_colorido_ativo' => 'boolean', 'certificado_a1' => 'integer',
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
        return $this->activeBackgroundFilename() !== null;
    }

    public function backgroundUrl(): ?string
    {
        $filename = $this->activeBackgroundFilename();
        return $filename ? asset('certificado/imagem_fundo/'.$filename) : null;
    }

    public function uploadedBackgroundExists(): bool { return $this->validBackgroundFile($this->fundo); }
    public function coloredBackgroundExists(): bool { return $this->validBackgroundFile($this->fundo_colorido); }
    public function gradientBackgroundExists(): bool { return $this->validBackgroundFile($this->fundo_degrade); }
    public function activeBackgroundFilename(): ?string
    {
        if ($this->tipo_fundo === 'degrade' && $this->gradientBackgroundExists()) return $this->fundo_degrade;
        if (($this->tipo_fundo === 'colorido' || $this->fundo_colorido_ativo) && $this->coloredBackgroundExists()) return $this->fundo_colorido;
        return $this->uploadedBackgroundExists() ? $this->fundo : null;
    }
    private function validBackgroundFile(?string $filename): bool
    {
        return filled($filename) && basename((string) $filename) === $filename && is_file(public_path('certificado/imagem_fundo/'.$filename));
    }
}
