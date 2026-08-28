@extends('layouts.app')
@section('title', $variavel->exists ? 'Editar variável' : 'Nova variável')
@push('styles')<style>.variable-preview{max-width:100%;max-height:22rem;border:1px solid #dee2e6;border-radius:.5rem;object-fit:contain}</style>@endpush
@section('content')
@php($imageExists = $variavel->imageExists())
<div class="mb-4"><h1 class="page-title">{{ $variavel->exists?'Editar variável':'Nova variável' }}</h1><p class="page-description mb-0">Configure o conteúdo e o posicionamento da variável.</p></div>
<form id="variavelForm" method="POST" enctype="multipart/form-data" action="{{ $variavel->exists?route('variaveis.update',$variavel):route('variaveis.store') }}">@csrf @if($variavel->exists) @method('PUT') @endif
<div class="card content-card"><div class="card-header"><h2 class="h5 fw-bold mb-0">Dados da variável</h2></div><div class="card-body p-4">
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="row g-3">
    <div class="col-12 col-md-4"><label for="tipo" class="form-label">Tipo *</label><select id="tipo" name="tipo" class="form-select" required><option value="">Selecione</option><option value="imagem" @selected(old('tipo',$variavel->tipo)==='imagem')>Imagem</option><option value="texto" @selected(old('tipo',$variavel->tipo)==='texto')>Texto</option></select></div>
    <div class="col-12 col-md-3"><label for="ativo" class="form-label">Status *</label><select id="ativo" name="ativo" class="form-select" required><option value="1" @selected((string)old('ativo',$variavel->exists?(int)$variavel->ativo:1)==='1')>Ativa</option><option value="0" @selected((string)old('ativo',$variavel->exists?(int)$variavel->ativo:1)==='0')>Inativa</option></select></div>

    <div id="imageFields" class="col-12 d-none">
        <label class="form-label">Imagem</label><input type="hidden" id="remover_imagem" name="remover_imagem" value="{{ old('remover_imagem',0) }}">
        @if($variavel->imagem && !$imageExists)<div id="missingImage" class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i>O arquivo <strong>{{ $variavel->imagem }}</strong> está registrado, mas não foi encontrado. Selecione uma nova imagem.</div>@endif
        <div id="previewArea" class="{{ $imageExists?'':'d-none' }}"><img id="imagePreview" class="variable-preview" @if($imageExists) src="{{ $variavel->imageUrl() }}" @endif alt="Miniatura da imagem"><div><button id="removeImage" type="button" class="btn btn-sm btn-outline-danger mt-2"><i class="bi bi-x-lg me-1"></i>Remover miniatura</button></div></div>
        <div id="uploadArea" class="{{ $imageExists?'d-none':'' }}"><input id="imagem" name="imagem" type="file" class="form-control" accept=".png,.jpg,.jpeg,image/png,image/jpeg"><div class="form-text">PNG, JPG ou JPEG, até 10 MB.</div></div>
    </div>

    <div id="textFields" class="col-12 d-none"><div class="row g-3">
        <div class="col-12"><label for="texto" class="form-label">Texto</label><textarea id="texto" name="texto" class="form-control text-only" rows="4">{{ old('texto',$variavel->texto) }}</textarea></div>
        <div class="col-12 col-md-4"><label for="alinhamento" class="form-label">Alinhamento</label><select id="alinhamento" name="alinhamento" class="form-select text-only"><option value="">Selecione</option>@foreach(['esquerda'=>'Esquerda','direita'=>'Direita','centralizado'=>'Centralizado','justificado'=>'Justificado'] as $value=>$label)<option value="{{ $value }}" @selected(old('alinhamento',$variavel->alinhamento)===$value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-12 col-md-4"><label for="cor" class="form-label">Cor</label><input id="cor" name="cor" class="form-control text-only" maxlength="15" placeholder="#000000" value="{{ old('cor',$variavel->cor) }}"></div>
    </div></div>

    @foreach([['pos_x','Posição X'],['pox_y','Posição Y'],['altura','Altura'],['largura','Largura'],['centro_x','Centro X'],['centro_y','Centro Y']] as [$field,$label])
        <div class="col-6 col-md-2"><label for="{{ $field }}" class="form-label">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" type="number" class="form-control" value="{{ old($field,$variavel->{$field}) }}" @if(in_array($field,['altura','largura'],true)) min="0" @endif></div>
    @endforeach
</div>
<div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('variaveis.index') }}" class="btn btn-outline-secondary">Cancelar</a><button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar</button></div>
</div></div></form>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const form=document.getElementById('variavelForm'),tipo=document.getElementById('tipo'),imageFields=document.getElementById('imageFields'),textFields=document.getElementById('textFields'),input=document.getElementById('imagem'),previewArea=document.getElementById('previewArea'),uploadArea=document.getElementById('uploadArea'),preview=document.getElementById('imagePreview'),remove=document.getElementById('removeImage'),flag=document.getElementById('remover_imagem'),missing=document.getElementById('missingImage');let objectUrl=null;
 const updateType=()=>{const isImage=tipo.value==='imagem';imageFields.classList.toggle('d-none',!isImage);textFields.classList.toggle('d-none',isImage);document.querySelectorAll('.text-only').forEach(field=>field.disabled=isImage)};
 const showUpload=()=>{if(objectUrl)URL.revokeObjectURL(objectUrl);objectUrl=null;input.value='';preview.removeAttribute('src');previewArea.classList.add('d-none');uploadArea.classList.remove('d-none');if(missing)missing.classList.add('d-none')};
 tipo.addEventListener('change',updateType);updateType();
 input.addEventListener('change',()=>{const file=input.files[0];if(!file)return;if(objectUrl)URL.revokeObjectURL(objectUrl);objectUrl=URL.createObjectURL(file);preview.src=objectUrl;previewArea.classList.remove('d-none');uploadArea.classList.add('d-none');flag.value='0';if(missing)missing.classList.add('d-none')});
 remove.addEventListener('click',()=>{showUpload();flag.value='1'});
 form.addEventListener('submit',()=>{if(tipo.value!=='imagem')flag.value='1'});
});
</script>
@endpush
