<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListaParticipante extends Model
{
    protected $table = 'lista_participantes';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public const CREATED_AT = 'criado_em';
    public const UPDATED_AT = 'alterado_em';
    protected $fillable = ['participante_id','novo_certificado_id','codigo','arquivo_pdf','dados_personalizados','snapshot_dados','snapshot_template','gerado_em','erro_geracao'];
    protected function casts(): array { return ['id'=>'integer','participante_id'=>'integer','novo_certificado_id'=>'integer','dados_personalizados'=>'array','snapshot_dados'=>'array','snapshot_template'=>'array','gerado_em'=>'datetime','criado_em'=>'datetime','alterado_em'=>'datetime']; }
    public function participante(): BelongsTo { return $this->belongsTo(Participante::class, 'participante_id')->withTrashed(); }
    public function novoCertificado(): BelongsTo { return $this->belongsTo(NovoCertificado::class, 'novo_certificado_id')->withTrashed(); }
}
