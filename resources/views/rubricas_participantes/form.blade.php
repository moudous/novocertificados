@extends('layouts.app')
@section('title',$rubrica->exists?'Editar rubrica':'Nova rubrica')
@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet"><link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css" rel="stylesheet">
<style>.signature-drop{padding:2rem;border:2px dashed #adb5bd;border-radius:.75rem;background:#f8f9fa;text-align:center;cursor:pointer}.signature-drop:hover{border-color:#0d6efd;background:#f2f7ff}.signature-editor{max-height:28rem;background:#eef1f5}.signature-editor img{display:block;max-width:100%}.signature-result{max-width:100%;max-height:16rem;object-fit:contain}</style>
@endpush
@section('content')
@php($signatureExists=$rubrica->signatureExists())
<div class="mb-4"><h1 class="page-title">{{ $rubrica->exists?'Editar rubrica':'Nova rubrica' }}</h1><p class="page-description mb-0">Vincule uma rubrica em PNG ao participante.</p></div>
<form id="rubricaForm" method="POST" enctype="multipart/form-data" action="{{ $rubrica->exists?route('rubricas_participantes.update',$rubrica):route('rubricas_participantes.store') }}">@csrf @if($rubrica->exists) @method('PUT') @endif
<div class="card content-card"><div class="card-header"><h2 class="h5 fw-bold mb-0">Dados da rubrica</h2></div><div class="card-body p-4">
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div id="editorAlert" class="alert alert-danger d-none"></div>
<div class="row g-3">
    <div class="col-12 col-md-8"><label for="participante_id" class="form-label">Participante</label><select id="participante_id" name="participante_id" class="form-select"><option value=""></option>@if(old('participante_id',$rubrica->participante_id))<option value="{{ old('participante_id',$rubrica->participante_id) }}" selected>{{ $rubrica->participante?->nome ?? 'Participante selecionado' }}</option>@endif</select></div>
    <div class="col-12 col-md-4"><label for="ativo" class="form-label">Status *</label><select id="ativo" name="ativo" class="form-select" required><option value="1" @selected((string)old('ativo',$rubrica->exists?(int)$rubrica->ativo:1)==='1')>Ativa</option><option value="0" @selected((string)old('ativo',$rubrica->exists?(int)$rubrica->ativo:1)==='0')>Inativa</option></select></div>
    <div class="col-12"><label class="form-label">Rubrica</label><input id="rubrica" name="rubrica" type="file" class="d-none" accept=".png,image/png"><input id="remover_rubrica" name="remover_rubrica" type="hidden" value="{{ old('remover_rubrica',0) }}">
        @if($rubrica->rubrica&&!$signatureExists)<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i>O arquivo <strong>{{ $rubrica->rubrica }}</strong> não foi encontrado. Envie uma nova rubrica.</div>@endif
        <div id="dropArea" class="signature-drop {{ $signatureExists?'d-none':'' }}" tabindex="0"><i class="bi bi-cloud-arrow-up fs-2 text-primary"></i><div class="fw-semibold mt-2">Clique para selecionar ou cole uma imagem aqui</div><div class="text-muted small">Somente PNG, com até 2 MB</div></div>
        <div id="editorArea" class="{{ $signatureExists?'':'d-none' }}">
            <div class="signature-editor rounded border overflow-hidden"><img id="editorImage" @if($signatureExists) src="{{ $rubrica->signatureUrl() }}" @endif alt="Editor da rubrica"></div>
            <div class="d-flex flex-wrap align-items-end gap-2 mt-3">
                <button id="applyCrop" type="button" class="btn btn-outline-primary"><i class="bi bi-crop me-1"></i>Aplicar recorte</button>
                <button id="removeBackground" type="button" class="btn btn-outline-secondary"><i class="bi bi-magic me-1"></i>Tentar remover fundo</button>
                <div><label for="tolerance" class="form-label small mb-0">Tolerância do fundo: <span id="toleranceValue">45</span></label><input id="tolerance" type="range" class="form-range" min="10" max="120" value="45" style="width:180px"></div>
                <button id="replaceImage" type="button" class="btn btn-outline-secondary"><i class="bi bi-arrow-repeat me-1"></i>Trocar imagem</button>
                <button id="deleteImage" type="button" class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i>Remover</button>
            </div>
            <div class="form-text">A remoção usa a cor dos cantos como fundo. Ajuste a tolerância e tente novamente se necessário.</div>
            <div id="processedArea" class="d-none mt-3"><div class="form-label fw-semibold">Resultado que será salvo</div><div class="border rounded bg-white p-2"><img id="processedImage" class="signature-result" alt="Resultado processado"></div></div>
        </div>
    </div>
</div>
<div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('rubricas_participantes.index') }}" class="btn btn-outline-secondary">Cancelar</a><button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar</button></div>
</div></div></form>
@endsection
@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script><script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script><script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 $('#participante_id').select2({theme:'bootstrap-5',width:'100%',placeholder:'Pesquise por nome ou e-mail',allowClear:true,ajax:{url:@json(route('rubricas_participantes.participantes',[],false)),dataType:'json',delay:250,data:p=>({q:p.term||'',page:p.page||1}),processResults:r=>r}});
 const input=document.getElementById('rubrica'),drop=document.getElementById('dropArea'),editorArea=document.getElementById('editorArea'),image=document.getElementById('editorImage'),processedArea=document.getElementById('processedArea'),processed=document.getElementById('processedImage'),flag=document.getElementById('remover_rubrica'),alertBox=document.getElementById('editorAlert'),tolerance=document.getElementById('tolerance'),toleranceValue=document.getElementById('toleranceValue');let cropper=null,objectUrl=null,processedUrl=null;
 const error=message=>{alertBox.textContent=message;alertBox.classList.remove('d-none')};const clearError=()=>alertBox.classList.add('d-none');
 const destroyUrls=()=>{if(objectUrl)URL.revokeObjectURL(objectUrl);if(processedUrl)URL.revokeObjectURL(processedUrl);objectUrl=null;processedUrl=null};
 const initCropper=()=>{if(cropper)cropper.destroy();cropper=new Cropper(image,{viewMode:1,autoCropArea:1,background:false,responsive:true});editorArea.classList.remove('d-none');drop.classList.add('d-none')};
 if(image.getAttribute('src')){if(image.complete)initCropper();else image.addEventListener('load',initCropper,{once:true})}
 const loadFile=file=>{clearError();if(file.type!=='image/png'&&!file.name.toLowerCase().endsWith('.png')){error('Selecione ou cole uma imagem no formato PNG.');return}if(file.size>2*1024*1024){error('A imagem deve ter no máximo 2 MB.');return}destroyUrls();objectUrl=URL.createObjectURL(file);image.onload=initCropper;image.src=objectUrl;processedArea.classList.add('d-none');flag.value='0';const transfer=new DataTransfer();transfer.items.add(file);input.files=transfer.files};
 const exportImage=removeBg=>{if(!cropper)return;clearError();const canvas=cropper.getCroppedCanvas({imageSmoothingEnabled:true,imageSmoothingQuality:'high'});if(removeBg){const context=canvas.getContext('2d'),pixels=context.getImageData(0,0,canvas.width,canvas.height),data=pixels.data,corners=[[0,0],[canvas.width-1,0],[0,canvas.height-1],[canvas.width-1,canvas.height-1]],background=[0,0,0];corners.forEach(([x,y])=>{const i=(y*canvas.width+x)*4;background[0]+=data[i]/4;background[1]+=data[i+1]/4;background[2]+=data[i+2]/4});const limit=Number(tolerance.value);for(let i=0;i<data.length;i+=4){const distance=Math.sqrt((data[i]-background[0])**2+(data[i+1]-background[1])**2+(data[i+2]-background[2])**2);if(distance<=limit)data[i+3]=0;else if(distance<limit+25)data[i+3]=Math.round(data[i+3]*(distance-limit)/25)}context.putImageData(pixels,0,0)}canvas.toBlob(blob=>{if(!blob){error('Não foi possível processar a imagem.');return}if(blob.size>2*1024*1024){error('O resultado ultrapassa 2 MB. Faça um recorte menor.');return}if(processedUrl)URL.revokeObjectURL(processedUrl);processedUrl=URL.createObjectURL(blob);processed.src=processedUrl;processedArea.classList.remove('d-none');const file=new File([blob],'rubrica.png',{type:'image/png'}),transfer=new DataTransfer();transfer.items.add(file);input.files=transfer.files;flag.value='0'},'image/png')};
 drop.addEventListener('click',()=>input.click());drop.addEventListener('keydown',event=>{if(event.key==='Enter'||event.key===' '){event.preventDefault();input.click()}});document.getElementById('replaceImage').addEventListener('click',()=>input.click());input.addEventListener('change',()=>{if(input.files[0])loadFile(input.files[0])});
 document.addEventListener('paste',event=>{const file=[...event.clipboardData.items].find(item=>item.type.startsWith('image/'))?.getAsFile();if(file){event.preventDefault();loadFile(file)}});
 document.getElementById('applyCrop').addEventListener('click',()=>exportImage(false));document.getElementById('removeBackground').addEventListener('click',()=>exportImage(true));tolerance.addEventListener('input',()=>toleranceValue.textContent=tolerance.value);
 document.getElementById('deleteImage').addEventListener('click',()=>{if(cropper){cropper.destroy();cropper=null}destroyUrls();input.value='';image.removeAttribute('src');processed.removeAttribute('src');processedArea.classList.add('d-none');editorArea.classList.add('d-none');drop.classList.remove('d-none');flag.value='1';clearError()});
});
</script>
@endpush
