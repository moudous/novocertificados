@extends('layouts.app')
@section('title', $atividade->exists ? 'Editar atividade' : 'Nova atividade')
@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet"><link href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css" rel="stylesheet">
<style>.background-preview{max-width:100%;max-height:22rem;border:1px solid #dee2e6;border-radius:.5rem}.CodeMirror{height:18rem;border:1px solid #dee2e6;border-radius:.375rem;font-size:.9rem}</style>
@endpush
@section('content')
@php($backgroundExists = $atividade->backgroundExists())
<div class="mb-4"><h1 class="page-title">{{ $atividade->exists ? 'Editar atividade' : 'Nova atividade' }}</h1><p class="page-description mb-0">Preencha os dados e configure o certificado.</p></div>
<form id="atividadeForm" method="POST" enctype="multipart/form-data" action="{{ $atividade->exists ? route('atividades.update', $atividade) : route('atividades.store') }}">@csrf @if($atividade->exists) @method('PUT') @endif
<div class="card content-card"><div class="card-header"><h2 class="h5 fw-bold mb-0">Dados da atividade</h2></div><div class="card-body p-4">
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="row g-3">
    <div class="col-12 col-md-6"><label for="nome" class="form-label">Nome</label><input id="nome" name="nome" class="form-control" maxlength="200" value="{{ old('nome',$atividade->nome) }}"></div>
    <div class="col-12 col-md-4"><label for="eventoId" class="form-label">Evento</label><select id="eventoId" name="eventoId" class="form-select"><option value=""></option>@if(old('eventoId',$atividade->eventoId))<option value="{{ old('eventoId',$atividade->eventoId) }}" selected>{{ $atividade->evento?->nome ?? 'Evento selecionado' }}</option>@endif</select></div>
    <div class="col-12 col-md-2"><label for="ativo" class="form-label">Status *</label><select id="ativo" name="ativo" class="form-select" required><option value="1" @selected((string)old('ativo',$atividade->exists?(int)$atividade->ativo:1)==='1')>Ativa</option><option value="0" @selected((string)old('ativo',$atividade->exists?(int)$atividade->ativo:1)==='0')>Inativa</option></select></div>
    <div class="col-12 col-md-4"><label for="periodos" class="form-label">Períodos</label><input id="periodos" name="periodos" class="form-control" maxlength="100" value="{{ old('periodos',$atividade->periodos) }}"></div>
    <div class="col-12 col-md-8"><label for="descricao_old" class="form-label">Descrição</label><textarea id="descricao_old" name="descricao_old" class="form-control" rows="3">{{ old('descricao_old',$atividade->descricao_old) }}</textarea></div>
    <div class="col-12"><label for="imagemFundo" class="form-label">Imagem de fundo</label><input id="imagemFundo" name="imagemFundo" type="file" class="form-control" accept=".pdf,.png,.jpg,.jpeg,application/pdf,image/png,image/jpeg"><div class="form-text">PDF, PNG, JPG ou JPEG, até 10 MB.</div><input type="hidden" id="remover_imagem" name="remover_imagem" value="0">
        @if($atividade->imagemFundo && !$backgroundExists)<div class="alert alert-warning mt-3 mb-0"><i class="bi bi-exclamation-triangle me-1"></i>O arquivo <strong>{{ $atividade->imagemFundo }}</strong> está registrado no banco, mas não foi encontrado em <code>certificado/imagem_fundo</code>. Você pode apagá-lo ou enviar outro arquivo.</div>@endif
        <div id="previewArea" class="mt-3 {{ $atividade->imagemFundo ? '' : 'd-none' }}">
            <img id="imagePreview" class="background-preview {{ $backgroundExists && strtolower(pathinfo($atividade->imagemFundo, PATHINFO_EXTENSION)) !== 'pdf' ? '' : 'd-none' }}" @if($backgroundExists) src="{{ $atividade->backgroundUrl() }}" @endif alt="Pré-visualização">
            <iframe id="pdfPreview" class="background-preview w-100 {{ $backgroundExists && strtolower(pathinfo($atividade->imagemFundo, PATHINFO_EXTENSION)) === 'pdf' ? '' : 'd-none' }}" style="height:22rem" @if($backgroundExists && strtolower(pathinfo($atividade->imagemFundo, PATHINFO_EXTENSION)) === 'pdf') src="{{ $atividade->backgroundUrl() }}" @endif title="Pré-visualização do PDF"></iframe>
            <div><button id="removeImage" type="button" class="btn btn-sm btn-outline-danger mt-2"><i class="bi bi-x-lg me-1"></i>Apagar imagem</button></div>
        </div>
    </div>
    <div class="col-12"><label for="template" class="form-label">Template</label><textarea id="template" name="template" rows="12" class="form-control font-monospace">{{ old('template',$atividade->template) }}</textarea></div>
</div>
<div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('atividades.index') }}" class="btn btn-outline-secondary">Cancelar</a><button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar</button></div>
</div></div></form>
@endsection
@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script><script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script><script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script><script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script><script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script><script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script><script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/htmlmixed/htmlmixed.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 $('#eventoId').select2({theme:'bootstrap-5',width:'100%',placeholder:'Pesquise um evento',allowClear:true,ajax:{url:@json(route('atividades.eventos', [], false)),dataType:'json',delay:250,data:p=>({q:p.term||'',page:p.page||1}),processResults:r=>r}});
 const editor=CodeMirror.fromTextArea(document.getElementById('template'),{mode:'htmlmixed',lineNumbers:true,lineWrapping:true});
 const input=document.getElementById('imagemFundo'),area=document.getElementById('previewArea'),img=document.getElementById('imagePreview'),pdf=document.getElementById('pdfPreview'),remove=document.getElementById('removeImage'),flag=document.getElementById('remover_imagem'); let objectUrl=null;
 const clearPreview=()=>{if(objectUrl)URL.revokeObjectURL(objectUrl);objectUrl=null;input.value='';img.removeAttribute('src');pdf.removeAttribute('src');img.classList.add('d-none');pdf.classList.add('d-none');area.classList.add('d-none');};
 input.addEventListener('change',()=>{const file=input.files[0];if(!file){clearPreview();return}if(objectUrl)URL.revokeObjectURL(objectUrl);objectUrl=URL.createObjectURL(file);area.classList.remove('d-none');flag.value='0';if(file.type==='application/pdf'||file.name.toLowerCase().endsWith('.pdf')){pdf.src=objectUrl;pdf.classList.remove('d-none');img.classList.add('d-none')}else{img.src=objectUrl;img.classList.remove('d-none');pdf.classList.add('d-none')}});
 remove.addEventListener('click',()=>{clearPreview();flag.value='1'});
 document.getElementById('atividadeForm').addEventListener('submit',()=>editor.save());
});
</script>
@endpush
