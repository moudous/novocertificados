<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ParticipanteTeste extends Model
{
    use SoftDeletes;

    protected $table = 'participantes_de_teste';

    public const CREATED_AT = 'criado_em';
    public const UPDATED_AT = 'alterado_em';
    public const DELETED_AT = 'apagado_em';

    protected $fillable = ['participante_id'];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'participante_id' => 'integer',
            'criado_em' => 'datetime',
            'alterado_em' => 'datetime',
            'apagado_em' => 'datetime',
        ];
    }

    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class, 'participante_id')->withTrashed();
    }
}
