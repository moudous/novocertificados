@extends('layouts.app')
@section('title', $certificado->exists ? 'Editar certificado A1' : 'Novo certificado A1')

@section('content')
<div class="mb-4"><h1 class="page-title">{{ $certificado->exists ? 'Editar certificado A1' : 'Novo certificado A1' }}</h1><p class="page-description mb-0">Preencha os dados do certificado A1.</p></div>
<form method="POST" action="{{ $certificado->exists ? route('certificados_a1.update', $certificado) : route('certificados_a1.store') }}">
    @csrf @if($certificado->exists) @method('PUT') @endif
    <div class="card content-card"><div class="card-header"><h2 class="h5 fw-bold mb-0">Dados do certificado A1</h2></div><div class="card-body p-4">
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <div class="row g-3">
            <div class="col-12 col-md-8"><label for="nome" class="form-label">Nome</label><input id="nome" name="nome" class="form-control" maxlength="50" value="{{ old('nome', $certificado->nome) }}"></div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('certificados_a1.index') }}" class="btn btn-outline-secondary">Cancelar</a><button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar</button></div>
    </div></div>
</form>
@endsection
