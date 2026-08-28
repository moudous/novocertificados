@extends('layouts.app')
@section('title','Visualizar variável')
@section('content')
@if(session('status'))<div class="alert alert-success alert-dismissible fade show">{{ session('status') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif
<div class="mb-4"><h1 class="page-title">Visualizar variável</h1><p class="page-description mb-0">Dados cadastrais da variável.</p></div>
<div class="card content-card"><div class="card-header"><h2 class="h5 fw-bold mb-0">Dados da variável</h2></div><div class="card-body p-4"><div class="row g-3">
@foreach([['ID',$variavel->id],['Nome',$variavel->nome],['Tipo',ucfirst((string)$variavel->tipo)],['Status',$variavel->ativo?'Ativa':'Inativa'],['Posição X',$variavel->pos_x],['Posição Y',$variavel->pox_y],['Altura',$variavel->altura],['Largura',$variavel->largura],['Centro X',$variavel->centro_x],['Centro Y',$variavel->centro_y],['Criada em',$variavel->criado_em?->format('d/m/Y H:i')],['Alterada em',$variavel->alterado_em?->format('d/m/Y H:i')]] as [$label,$value])<div class="col-12 col-md-4"><div class="form-label fw-semibold">{{ $label }}</div><div class="form-control bg-body-tertiary h-auto text-break">{{ filled($value)?$value:'—' }}</div></div>@endforeach
@if($variavel->tipo==='texto')
<div class="col-12 col-md-6"><div class="form-label fw-semibold">Alinhamento</div><div class="form-control bg-body-tertiary h-auto">{{ ucfirst((string)$variavel->alinhamento) ?: '—' }}</div></div><div class="col-12 col-md-6"><div class="form-label fw-semibold">Cor</div><div class="form-control bg-body-tertiary h-auto">{{ $variavel->cor ?: '—' }}</div></div><div class="col-12"><div class="form-label fw-semibold">Texto</div><div class="form-control bg-body-tertiary h-auto" style="white-space:pre-wrap">{{ $variavel->texto ?: '—' }}</div></div>
@elseif($variavel->imageExists())
<div class="col-12"><div class="form-label fw-semibold">Imagem</div><img src="{{ $variavel->imageUrl() }}" class="img-fluid border rounded" style="max-height:32rem" alt="Imagem da variável"></div>
@elseif($variavel->imagem)
<div class="col-12"><div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle me-1"></i>O arquivo <strong>{{ $variavel->imagem }}</strong> está registrado, mas não foi encontrado.</div></div>
@endif
</div><div class="d-flex justify-content-end gap-2 mt-4">@if(in_array('variaveis.editar',(array)session('gi_context.permissoes',[]),true))<a href="{{ route('variaveis.edit',$variavel) }}" class="btn btn-primary"><i class="bi bi-pencil-fill me-1"></i>Editar</a>@endif<a href="{{ route('variaveis.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Voltar</a></div></div></div>
@endsection
