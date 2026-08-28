<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssinaturaTemplate extends Model
{
    use SoftDeletes;

    protected $table = 'assinaturas_template';
    public const CREATED_AT = 'criado_em';
    public const UPDATED_AT = 'alterado_em';
    public const DELETED_AT = 'apgado_em';
    protected $fillable = ['participante_id', 'template_id', 'titulacao', 'rubrica_id', 'ativo'];

    protected function casts(): array
    {
        return ['id'=>'integer','participante_id'=>'integer','template_id'=>'integer','rubrica_id'=>'integer','ativo'=>'boolean','criado_em'=>'datetime','alterado_em'=>'datetime','apgado_em'=>'datetime'];
    }

    public function participante(): BelongsTo { return $this->belongsTo(Participante::class, 'participante_id')->withTrashed(); }
    public function template(): BelongsTo { return $this->belongsTo(Template::class, 'template_id')->withTrashed(); }
    public function rubrica(): BelongsTo { return $this->belongsTo(RubricaParticipante::class, 'rubrica_id')->withTrashed(); }
}
