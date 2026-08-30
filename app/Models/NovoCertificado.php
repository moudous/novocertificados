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
    protected $fillable = ['nome','certificado_antigo_id','lista_participantes_id','template_id','evento_id','atividade_id','responsavel_id','rubrica_id','data_emissao','campos_personalizados','ativo'];
    protected function casts(): array { return ['id'=>'integer','certificado_antigo_id'=>'integer','lista_participantes_id'=>'integer','template_id'=>'integer','evento_id'=>'integer','atividade_id'=>'integer','responsavel_id'=>'integer','rubrica_id'=>'integer','data_emissao'=>'date','campos_personalizados'=>'array','ativo'=>'boolean','criado_em'=>'datetime','alterado_em'=>'datetime','apagado_em'=>'datetime']; }
    public function certificadoAntigo(): BelongsTo { return $this->belongsTo(Certificado::class, 'certificado_antigo_id')->withTrashed(); }
    public function template(): BelongsTo { return $this->belongsTo(Template::class, 'template_id')->withTrashed(); }
    public function participantes(): HasMany { return $this->hasMany(ListaParticipante::class, 'novo_certificado_id'); }
    public function evento(): BelongsTo { return $this->belongsTo(Evento::class, 'evento_id')->withTrashed(); }
    public function atividade(): BelongsTo { return $this->belongsTo(Atividade::class, 'atividade_id')->withTrashed(); }
    public function responsavel(): BelongsTo { return $this->belongsTo(Responsavel::class, 'responsavel_id')->withTrashed(); }
    public function rubrica(): BelongsTo { return $this->belongsTo(RubricaParticipante::class, 'rubrica_id')->withTrashed(); }
}
