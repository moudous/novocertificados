@extends('layouts.app')
@section('title',$registro->exists?'Editar participante de teste':'Novo participante de teste')
@push('styles')<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">@endpush
@section('content')
<div class="mb-4"><h1 class="page-title">{{ $registro->exists?'Editar participante de teste':'Novo participante de teste' }}</h1><p class="page-description mb-0">Selecione o participante que fará parte dos testes.</p></div>
<form method="POST" action="{{ $registro->exists?route('participantes_teste.update',$registro):route('participantes_teste.store') }}">@csrf @if($registro->exists) @method('PUT') @endif
<div class="card content-card"><div class="card-header"><h2 class="h5 fw-bold mb-0">Dados do participante de teste</h2></div><div class="card-body p-4">
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="row g-3"><div class="col-12 col-md-8"><label for="participante_id" class="form-label">Participante *</label><select id="participante_id" name="participante_id" class="form-select" required><option value=""></option>@if(old('participante_id',$registro->participante_id))<option value="{{ old('participante_id',$registro->participante_id) }}" selected>{{ $registro->participante?->nome ?? 'Participante selecionado' }}</option>@endif</select></div></div>
<div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('participantes_teste.index') }}" class="btn btn-outline-secondary">Cancelar</a><button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar</button></div>
</div></div></form>
@endsection
@push('scripts')<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script><script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script><script>document.addEventListener('DOMContentLoaded',()=>{$('#participante_id').select2({theme:'bootstrap-5',width:'100%',placeholder:'Pesquise por nome ou e-mail',allowClear:true,ajax:{url:@json(route('participantes_teste.participantes',[],false)),dataType:'json',delay:250,data:p=>({q:p.term||'',page:p.page||1}),processResults:r=>r}})});</script>@endpush
