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
    protected $fillable = ['participante_id','novo_certificado_id','ativo','codigo','codigo_img','arquivo_pdf','arquivo_img','dados_personalizados','snapshot_dados','snapshot_template','gerado_em','erro_geracao'];
    protected function casts(): array { return ['id'=>'integer','participante_id'=>'integer','novo_certificado_id'=>'integer','ativo'=>'boolean','dados_personalizados'=>'array','snapshot_dados'=>'array','snapshot_template'=>'array','gerado_em'=>'datetime','criado_em'=>'datetime','alterado_em'=>'datetime']; }
    public function participante(): BelongsTo { return $this->belongsTo(Participante::class, 'participante_id')->withTrashed(); }
    public function novoCertificado(): BelongsTo { return $this->belongsTo(NovoCertificado::class, 'novo_certificado_id')->withTrashed(); }
    public function arquivoPath(): string { return public_path('certificado/emitidos/'.basename((string) $this->arquivo_pdf)); }
    public function arquivoExists(): bool { return filled($this->arquivo_pdf) && basename((string) $this->arquivo_pdf) === $this->arquivo_pdf && is_file($this->arquivoPath()); }
    public function imagemPath(): string { return storage_path('app/private/certificados-imagens/'.basename((string) $this->arquivo_img)); }
    public function imagemExists(): bool { return filled($this->arquivo_img) && basename((string) $this->arquivo_img) === $this->arquivo_img && is_file($this->imagemPath()); }
}
