@extends('layouts.app')
@section('title', 'Visualizar evento')

@section('content')
@if(session('status'))<div class="alert alert-success alert-dismissible fade show">{{ session('status') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif
<div class="mb-4"><h1 class="page-title">Visualizar evento</h1><p class="page-description mb-0">Dados cadastrais do evento.</p></div>
<div class="card content-card"><div class="card-header"><h2 class="h5 fw-bold mb-0">Dados do evento</h2></div><div class="card-body p-4">
    <div class="row g-3">
        @foreach([['ID', $evento->id], ['Nome', $evento->nome], ['Períodos', $evento->periodos], ['Status', $evento->ativo ? 'Ativo' : 'Inativo'], ['Criado em', $evento->criado_em?->format('d/m/Y H:i')], ['Atualizado em', $evento->atualizado_em?->format('d/m/Y H:i')]] as [$label, $value])
            <div class="col-12 col-md-6"><div class="form-label fw-semibold">{{ $label }}</div><div class="form-control bg-body-tertiary h-auto text-break">{{ filled($value) ? $value : '—' }}</div></div>
        @endforeach
        <div class="col-12"><div class="form-label fw-semibold">Descrição</div><div class="form-control bg-body-tertiary h-auto text-break" style="min-height: 5rem; white-space: pre-wrap">{{ filled($evento->descricao) ? $evento->descricao : '—' }}</div></div>
    </div>
    <div class="d-flex justify-content-end gap-2 mt-4">@if(in_array('eventos.editar', (array)session('gi_context.permissoes', []), true))<a href="{{ route('eventos.edit', $evento) }}" class="btn btn-primary"><i class="bi bi-pencil-fill me-1"></i>Editar</a>@endif<a href="{{ route('eventos.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Voltar</a></div>
</div></div>
@endsection
