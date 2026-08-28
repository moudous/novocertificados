@extends('layouts.app')
@section('title', $evento->exists ? 'Editar evento' : 'Novo evento')

@section('content')
<div class="mb-4"><h1 class="page-title">{{ $evento->exists ? 'Editar evento' : 'Novo evento' }}</h1><p class="page-description mb-0">Preencha os dados do evento.</p></div>
<form method="POST" action="{{ $evento->exists ? route('eventos.update', $evento) : route('eventos.store') }}">
    @csrf @if($evento->exists) @method('PUT') @endif
    <div class="card content-card"><div class="card-header"><h2 class="h5 fw-bold mb-0">Dados do evento</h2></div><div class="card-body p-4">
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <div class="row g-3">
            <div class="col-12 col-md-7"><label for="nome" class="form-label">Nome</label><input id="nome" name="nome" class="form-control" maxlength="200" value="{{ old('nome', $evento->nome) }}"></div>
            <div class="col-12 col-md-3"><label for="periodos" class="form-label">Períodos</label><input id="periodos" name="periodos" class="form-control" maxlength="100" value="{{ old('periodos', $evento->periodos) }}"></div>
            <div class="col-12 col-md-2"><label for="ativo" class="form-label">Status *</label><select id="ativo" name="ativo" class="form-select" required><option value="1" @selected((string)old('ativo', $evento->exists ? (int)$evento->ativo : 1) === '1')>Ativo</option><option value="0" @selected((string)old('ativo', $evento->exists ? (int)$evento->ativo : 1) === '0')>Inativo</option></select></div>
            <div class="col-12"><label for="descricao" class="form-label">Descrição</label><textarea id="descricao" name="descricao" class="form-control" rows="3">{{ old('descricao', $evento->descricao) }}</textarea></div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('eventos.index') }}" class="btn btn-outline-secondary">Cancelar</a><button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar</button></div>
    </div></div>
</form>
@endsection
