<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FonteLayout extends Model
{
    protected $table = 'fontes_layout';
    public const CREATED_AT = 'criado_em';
    public const UPDATED_AT = 'alterado_em';
    protected $fillable = ['nome', 'arquivo', 'nome_original'];

    public function url(): string { return asset('certificado/fontes/'.$this->arquivo); }
    public function path(): string { return public_path('certificado/fontes/'.$this->arquivo); }
}
