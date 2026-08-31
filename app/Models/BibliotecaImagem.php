<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BibliotecaImagem extends Model
{
    use SoftDeletes;

    protected $table = 'biblioteca_imagens';
    public const CREATED_AT = 'criado_em';
    public const UPDATED_AT = 'alterado_em';
    public const DELETED_AT = 'apagado_em';
    protected $fillable = ['nome','categoria','arquivo','mime_type','largura_px','altura_px','tamanho','svg','ativo'];
    protected function casts(): array { return ['ativo'=>'boolean','largura_px'=>'integer','altura_px'=>'integer','tamanho'=>'integer','criado_em'=>'datetime','alterado_em'=>'datetime','apagado_em'=>'datetime']; }
    public function path(): string { return public_path('certificado/biblioteca/'.$this->arquivo); }
    public function url(): ?string { return $this->exists && is_file($this->path()) ? asset('certificado/biblioteca/'.$this->arquivo) : null; }
}
