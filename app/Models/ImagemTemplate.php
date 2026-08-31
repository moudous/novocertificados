<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImagemTemplate extends Model
{
    protected $table='imagens_template';
    public const CREATED_AT='criado_em';
    public const UPDATED_AT='alterado_em';
    protected $fillable=['template_id','biblioteca_imagem_id','element_uid','nome','svg'];
    protected function casts(): array{return ['template_id'=>'integer','biblioteca_imagem_id'=>'integer'];}
    public function template(): BelongsTo{return $this->belongsTo(Template::class);}
    public function imagemBiblioteca(): BelongsTo{return $this->belongsTo(BibliotecaImagem::class,'biblioteca_imagem_id')->withTrashed();}
    public function dataUrl(): string{return 'data:image/svg+xml;base64,'.base64_encode($this->svg);}
}
