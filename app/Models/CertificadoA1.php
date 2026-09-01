<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class CertificadoA1 extends Model
{
    use SoftDeletes;

    protected $table = 'certificados_a1';

    public const CREATED_AT = 'criado_em';
    public const UPDATED_AT = 'alterado_em';
    public const DELETED_AT = 'apagado_em';

    protected $fillable = ['nome','criado_por','arquivo','nome_arquivo_original','senha','titular','impressao_digital','valido_de','valido_ate'];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'criado_por' => 'integer',
            'senha' => 'encrypted',
            'valido_de' => 'datetime',
            'valido_ate' => 'datetime',
            'criado_em' => 'datetime',
            'alterado_em' => 'datetime',
            'apagado_em' => 'datetime',
        ];
    }

    public function certificateExists(): bool{return filled($this->arquivo)&&Storage::disk('local')->exists('certificados-a1/'.$this->arquivo);}
    public function certificatePath(): ?string{return $this->certificateExists()?Storage::disk('local')->path('certificados-a1/'.$this->arquivo):null;}
}
