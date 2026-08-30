@extends('layouts.app')
@section('title',$responsavel->exists?'Editar responsável':'Novo responsável')
@push('styles')<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">@endpush
@section('content')
<div class="mb-4"><h1 class="page-title">{{ $responsavel->exists?'Editar responsável':'Novo responsável' }}</h1><p class="page-description mb-0">Selecione um participante e informe seus dados como responsável.</p></div>
<form method="POST" action="{{ $responsavel->exists?route('responsaveis.update',$responsavel):route('responsaveis.store') }}">@csrf @if($responsavel->exists) @method('PUT') @endif
<div class="card content-card"><div class="card-header"><h2 class="h5 fw-bold mb-0">Dados do responsável</h2></div><div class="card-body p-4">
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="row g-3">
    <div class="col-12"><label for="participante_id" class="form-label">Participante *</label><select id="participante_id" name="participante_id" class="form-select" required><option value=""></option>@if(old('participante_id',$responsavel->participante_id))<option value="{{ old('participante_id',$responsavel->participante_id) }}" selected>{{ $responsavel->participante?->nome ?? 'Participante selecionado' }}</option>@endif</select></div>
    <div class="col-12 col-md-5"><label for="cargo" class="form-label">Cargo ou função</label><input id="cargo" name="cargo" maxlength="100" class="form-control" value="{{ old('cargo',$responsavel->cargo) }}" placeholder="Ex.: Professor, instrutor, coordenador"></div>
    <div class="col-12 col-md-5"><label for="titulacao" class="form-label">Titulação</label><input id="titulacao" name="titulacao" maxlength="100" class="form-control" value="{{ old('titulacao',$responsavel->titulacao) }}" placeholder="Ex.: Prof., Dr., Especialista"></div>
    <div class="col-12 col-md-2"><label for="ativo" class="form-label">Status *</label><select id="ativo" name="ativo" class="form-select" required><option value="1" @selected((string)old('ativo',$responsavel->exists?(int)$responsavel->ativo:1)==='1')>Ativo</option><option value="0" @selected((string)old('ativo',$responsavel->exists?(int)$responsavel->ativo:1)==='0')>Inativo</option></select></div>
</div>
<div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('responsaveis.index') }}" class="btn btn-outline-secondary">Cancelar</a><button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar</button></div>
</div></div></form>
@endsection
@push('scripts')<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script><script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script><script>document.addEventListener('DOMContentLoaded',()=>{$('#participante_id').select2({theme:'bootstrap-5',width:'100%',placeholder:'Pesquise por nome ou e-mail',ajax:{url:@json(route('responsaveis.participantes',[],false)),dataType:'json',delay:250,data:p=>({q:p.term||'',page:p.page||1,responsavel_id:@json($responsavel->id)}),processResults:r=>r}})});</script>@endpush
