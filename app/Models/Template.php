<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Template extends Model
{
    use SoftDeletes;

    protected $table = 'templates';

    public const CREATED_AT = 'crido_em';
    public const UPDATED_AT = 'alterado_em';
    public const DELETED_AT = 'apagado_em';

    protected $fillable = ['nome', 'criado_por', 'fundo', 'biblioteca_imagem_id', 'fundo_colorido', 'cor_fundo', 'fundo_colorido_ativo', 'tipo_fundo', 'fundo_degrade', 'cor_degrade_inicio', 'cor_degrade_fim', 'direcao_degrade', 'ativo', 'certificado_a1', 'largura', 'altura', 'pagina', 'layout_pagina', 'elementos_layout', 'campos_dinamicos', 'responsaveis'];

    protected function casts(): array
    {
        return [
            'id' => 'integer', 'criado_por' => 'integer', 'ativo' => 'boolean', 'fundo_colorido_ativo' => 'boolean', 'certificado_a1' => 'integer', 'biblioteca_imagem_id' => 'integer',
            'largura' => 'integer', 'altura' => 'integer', 'crido_em' => 'datetime',
            'alterado_em' => 'datetime', 'apagado_em' => 'datetime', 'elementos_layout' => 'array', 'campos_dinamicos' => 'array',
        ];
    }

    public function certificadoA1(): BelongsTo
    {
        return $this->belongsTo(CertificadoA1::class, 'certificado_a1')->withTrashed();
    }

    public function imagemBiblioteca(): BelongsTo
    {
        return $this->belongsTo(BibliotecaImagem::class, 'biblioteca_imagem_id')->withTrashed();
    }

    public function imagensTemplate(): HasMany { return $this->hasMany(ImagemTemplate::class); }

    public function hasParticipantCertificates(): bool
    {
        return DB::table('lista_participantes as lp')
            ->join('novos_certificados as nc', 'nc.id', '=', 'lp.novo_certificado_id')
            ->where('nc.template_id', $this->id)
            ->exists();
    }

    public function usedTemplateFields(): array
    {
        $used = [];
        foreach ($this->elementos_layout ?? [] as $element) {
            if (($element['source_type'] ?? null) === 'dynamic' && str_starts_with((string) ($element['source_key'] ?? ''), 'template.')) $used[] = substr($element['source_key'], 9);
            preg_match_all('/\{\{\s*template\.([a-z0-9_]+)\s*\}\}/i', (string) ($element['content'] ?? ''), $matches);
            $used = array_merge($used, $matches[1] ?? []);
        }
        $used = array_flip(array_unique($used));
        return collect($this->campos_dinamicos ?? [])->filter(fn (array $field) => isset($used[$field['nome'] ?? '']))->values()->all();
    }

    public function backgroundExists(): bool
    {
        return $this->activeBackgroundFilename() !== null;
    }

    public function backgroundUrl(): ?string
    {
        if ($this->tipo_fundo === 'biblioteca' && $this->imagemBiblioteca?->url()) return $this->imagemBiblioteca->url();
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
