@extends('layouts.app')
@section('title', 'Visualizar participante')

@section('content')
@if(session('status'))<div class="alert alert-success alert-dismissible fade show">{{ session('status') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif
<div class="mb-4"><h1 class="page-title">Visualizar participante</h1><p class="page-description mb-0">Dados cadastrais do participante.</p></div>
<div class="card content-card"><div class="card-header"><h2 class="h5 fw-bold mb-0">Dados do participante</h2></div><div class="card-body p-4">
    @php($fields = [['ID', $participante->id], ['Nome', $participante->nome], ['E-mail', $participante->email], ['CPF', $participante->cpf], ['Sexo', $participante->sexo], ['Grupo', $participante->grupo], ['Status', $participante->ativo ? 'Ativo' : 'Inativo'], ['Criado em', $participante->criado_em?->format('d/m/Y H:i')], ['Atualizado em', $participante->atualizado_em?->format('d/m/Y H:i')]])
    <div class="row g-3">@foreach($fields as [$label, $value])<div class="col-12 col-md-6"><div class="form-label fw-semibold">{{ $label }}</div><div class="form-control bg-body-tertiary h-auto text-break">{{ filled($value) ? $value : '—' }}</div></div>@endforeach</div>
    <div class="d-flex justify-content-end gap-2 mt-4">
        @if(in_array('participantes.editar', (array)session('gi_context.permissoes', []), true))<a href="{{ route('participantes.edit', ['id' => $participante->id, 'nome' => $participante->nome]) }}" class="btn btn-primary"><i class="bi bi-pencil-fill me-1"></i>Editar</a>@endif
        <a href="{{ route('participantes.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
    </div>
</div></div>
@endsection
