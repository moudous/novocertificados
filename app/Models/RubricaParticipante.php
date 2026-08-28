<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RubricaParticipante extends Model
{
    use SoftDeletes;

    protected $table = 'rubricas_participantes';

    public const CREATED_AT = 'criado_em';
    public const UPDATED_AT = 'alterado_em';
    public const DELETED_AT = 'apagado_em';

    protected $fillable = ['rubrica', 'participante_id', 'ativo'];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'participante_id' => 'integer',
            'ativo' => 'boolean',
            'criado_em' => 'datetime',
            'alterado_em' => 'datetime',
            'apagado_em' => 'datetime',
        ];
    }

    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class, 'participante_id')->withTrashed();
    }

    public function signatureExists(): bool
    {
        return filled($this->rubrica)
            && basename((string) $this->rubrica) === $this->rubrica
            && is_file(public_path('certificado/rubricas_participantes/'.$this->rubrica));
    }

    public function signatureUrl(): ?string
    {
        return $this->signatureExists()
            ? asset('certificado/rubricas_participantes/'.$this->rubrica)
            : null;
    }
}
