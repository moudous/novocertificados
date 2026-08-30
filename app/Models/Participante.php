<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Participante extends Model
{
    use SoftDeletes;

    protected $table = 'participantes';

    public const CREATED_AT = 'criado_em';
    public const UPDATED_AT = 'atualizado_em';
    public const DELETED_AT = 'excluido_em';

    protected $fillable = ['nome', 'email', 'sexo', 'grupo', 'ativo', 'cpf'];

    protected $hidden = ['email_ficticio'];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'ativo' => 'boolean',
            'criado_em' => 'datetime',
            'atualizado_em' => 'datetime',
            'excluido_em' => 'datetime',
        ];
    }

    protected function setKeysForSelectQuery($query): mixed
    {
        return $query
            ->where('id', $this->getAttribute('id'))
            ->where('nome', $this->getOriginal('nome', $this->getAttribute('nome')));
    }

    protected function setKeysForSaveQuery($query): mixed
    {
        return $this->setKeysForSelectQuery($query);
    }

    public function scopeWhereIdentity(Builder $query, int $id, string $nome): Builder
    {
        return $query->where('id', $id)->where('nome', $nome);
    }

    public function certificados(): HasMany
    {
        return $this->hasMany(Certificado::class, 'participanteId', 'id')->withTrashed();
    }

    public function responsavel(): HasOne
    {
        return $this->hasOne(Responsavel::class, 'participante_id');
    }

    public function rubricas(): HasMany
    {
        return $this->hasMany(RubricaParticipante::class, 'participante_id');
    }
}
