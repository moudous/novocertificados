@extends('layouts.app')
@section('title','Visualizar responsável')
@section('content')
@if(session('status'))<div class="alert alert-success alert-dismissible fade show">{{ session('status') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif
<div class="mb-4"><h1 class="page-title">Visualizar responsável</h1><p class="page-description mb-0">Dados do participante responsável.</p></div>
<div class="card content-card"><div class="card-header"><h2 class="h5 fw-bold mb-0">Dados do responsável</h2></div><div class="card-body p-4"><div class="row g-3">
@foreach([['ID',$responsavel->id],['Participante',$responsavel->participante?->nome],['E-mail',$responsavel->participante?->email],['Cargo ou função',$responsavel->cargo],['Titulação',$responsavel->titulacao],['Status',$responsavel->ativo?'Ativo':'Inativo'],['Criado em',$responsavel->criado_em?->format('d/m/Y H:i')],['Alterado em',$responsavel->alterado_em?->format('d/m/Y H:i')]] as [$label,$value])<div class="col-12 col-md-6"><div class="form-label fw-semibold">{{ $label }}</div><div class="form-control bg-body-tertiary h-auto text-break">{{ filled($value)?$value:'—' }}</div></div>@endforeach
</div><div class="d-flex justify-content-end gap-2 mt-4">@if(in_array('responsaveis.editar',(array)session('gi_context.permissoes',[]),true))<a href="{{ route('responsaveis.edit',$responsavel) }}" class="btn btn-primary"><i class="bi bi-pencil-fill me-1"></i>Editar</a>@endif<a href="{{ route('responsaveis.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Voltar</a></div></div></div>
@endsection
