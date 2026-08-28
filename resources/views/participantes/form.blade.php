@extends('layouts.app')
@section('title', $participante->exists ? 'Editar participante' : 'Novo participante')

@section('content')
<div class="mb-4"><h1 class="page-title">{{ $participante->exists ? 'Editar participante' : 'Novo participante' }}</h1><p class="page-description mb-0">Preencha os dados do participante.</p></div>
@php($params = ['id' => $participante->id, 'nome' => $participante->getOriginal('nome', $participante->nome)])
<form method="POST" action="{{ $participante->exists ? route('participantes.update', $params) : route('participantes.store') }}">
    @csrf @if($participante->exists) @method('PUT') @endif
    <div class="card content-card"><div class="card-header"><h2 class="h5 fw-bold mb-0">Dados do participante</h2></div><div class="card-body p-4">
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <div class="row g-3">
            <div class="col-12 col-md-8"><label for="nome" class="form-label">Nome *</label><input id="nome" name="nome" class="form-control" maxlength="100" required value="{{ old('nome', $participante->nome) }}"></div>
            <div class="col-12 col-md-4"><label for="cpf" class="form-label">CPF</label><input id="cpf" name="cpf" class="form-control" maxlength="11" inputmode="numeric" value="{{ old('cpf', $participante->cpf) }}"><div class="form-text">Informe somente os 11 números.</div></div>
            <div class="col-12 col-md-6"><label for="email" class="form-label">E-mail</label><input id="email" type="email" name="email" class="form-control" maxlength="150" value="{{ old('email', $participante->email) }}"></div>
            <div class="col-6 col-md-2"><label for="sexo" class="form-label">Sexo</label><select id="sexo" name="sexo" class="form-select"><option value="">Não informado</option><option value="M" @selected(strtoupper((string) old('sexo', $participante->sexo)) === 'M')>Masculino</option><option value="F" @selected(strtoupper((string) old('sexo', $participante->sexo)) === 'F')>Feminino</option></select></div>
            <div class="col-6 col-md-2"><label for="grupo" class="form-label">Grupo</label><input id="grupo" name="grupo" class="form-control text-uppercase" maxlength="1" value="{{ old('grupo', $participante->grupo) }}"></div>
            <div class="col-12 col-md-2"><label for="ativo" class="form-label">Status *</label><select id="ativo" name="ativo" class="form-select" required><option value="1" @selected((string)old('ativo', $participante->exists ? (int)$participante->ativo : 1) === '1')>Ativo</option><option value="0" @selected((string)old('ativo', $participante->exists ? (int)$participante->ativo : 1) === '0')>Inativo</option></select></div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('participantes.index') }}" class="btn btn-outline-secondary">Cancelar</a><button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar</button></div>
    </div></div>
</form>
@endsection
