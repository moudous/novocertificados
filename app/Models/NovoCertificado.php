<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NovoCertificado extends Model
{
    use SoftDeletes;
    protected $table = 'novos_certificados';
    public const CREATED_AT = 'criado_em';
    public const UPDATED_AT = 'alterado_em';
    public const DELETED_AT = 'apagado_em';
    protected $fillable = ['certificado_antigo_id','lista_participantes_id','template_id','ativo'];
    protected function casts(): array { return ['id'=>'integer','certificado_antigo_id'=>'integer','lista_participantes_id'=>'integer','template_id'=>'integer','ativo'=>'boolean','criado_em'=>'datetime','alterado_em'=>'datetime','apagado_em'=>'datetime']; }
    public function certificadoAntigo(): BelongsTo { return $this->belongsTo(Certificado::class, 'certificado_antigo_id')->withTrashed(); }
    public function template(): BelongsTo { return $this->belongsTo(Template::class, 'template_id')->withTrashed(); }
    public function participantes(): HasMany { return $this->hasMany(ListaParticipante::class, 'novo_certificado_id'); }
}
