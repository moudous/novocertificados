@extends('layouts.app')
@section('title','Visualizar participante de teste')
@section('content')
@if(session('status'))<div class="alert alert-success alert-dismissible fade show">{{ session('status') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif
<div class="mb-4"><h1 class="page-title">Visualizar participante de teste</h1><p class="page-description mb-0">Dados do participante selecionado.</p></div>
<div class="card content-card"><div class="card-header"><h2 class="h5 fw-bold mb-0">Dados do participante</h2></div><div class="card-body p-4"><div class="row g-3">
@foreach([['ID',$registro->id],['Participante',$registro->participante?->nome],['E-mail',$registro->participante?->email],['Criado em',$registro->criado_em?->format('d/m/Y H:i')],['Alterado em',$registro->alterado_em?->format('d/m/Y H:i')]] as [$label,$value])<div class="col-12 col-md-6"><div class="form-label fw-semibold">{{ $label }}</div><div class="form-control bg-body-tertiary h-auto text-break">{{ filled($value)?$value:'—' }}</div></div>@endforeach
</div><div class="d-flex justify-content-end gap-2 mt-4">@if(in_array('participantes_teste.editar',(array)session('gi_context.permissoes',[]),true))<a href="{{ route('participantes_teste.edit',$registro) }}" class="btn btn-primary"><i class="bi bi-pencil-fill me-1"></i>Editar</a>@endif<a href="{{ route('participantes_teste.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Voltar</a></div></div></div>
@endsection
