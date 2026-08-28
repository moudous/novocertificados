@extends('layouts.app')
@section('title','Construtor de layouts')
@push('styles')
<style>
.builder-page{height:calc(100vh - 50px);min-height:650px;display:flex;flex-direction:column}.builder-header{flex:0 0 auto}.builder-workspace{display:grid;grid-template-columns:minmax(230px,20%) minmax(0,80%);gap:1rem;min-height:0;flex:1}.builder-sidebar{min-height:0;overflow-y:auto}.builder-canvas-area{min-width:0;min-height:0;overflow:auto;background:#e8ebf0;border-radius:14px;padding:1.25rem;display:flex;align-items:flex-start;justify-content:center}.layout-canvas{position:relative;width:min(100%,calc((100vh - 190px) * var(--ratio)));aspect-ratio:var(--ratio);flex:0 0 auto;background:#fff center/100% 100% no-repeat;box-shadow:0 8px 25px #24304726;overflow:hidden;user-select:none}.layout-element{position:absolute;min-width:8px;min-height:8px;border:1px dashed transparent;cursor:move;overflow:hidden}.layout-element.selected{border-color:#0d6efd;box-shadow:0 0 0 2px #0d6efd26}.layout-element img{width:100%;height:100%;display:block;object-fit:contain;pointer-events:none}.layout-element-text{white-space:pre-wrap;line-height:1.15;font-size:clamp(8px,1.25vw,18px);padding:2px}.resize-handle{display:none;position:absolute;right:-1px;bottom:-1px;width:12px;height:12px;background:#0d6efd;border:2px solid #fff;cursor:nwse-resize}.layout-element.selected.image .resize-handle{display:block}.variable-button{text-align:left;width:100%;border:1px solid #dee2e6;background:#fff;border-radius:.6rem;padding:.65rem;display:flex;align-items:center;gap:.6rem}.variable-button:hover{border-color:#0d6efd;background:#f6f9ff}.variable-thumb{width:34px;height:34px;object-fit:contain;border-radius:.3rem}.empty-background{position:absolute;inset:0;display:grid;place-items:center;color:#8490a4;pointer-events:none}.properties-panel{border-top:1px solid #e5e7eb;margin-top:1rem;padding-top:1rem}.sidebar-sticky-title{position:sticky;top:0;background:#fff;z-index:2;padding-bottom:.5rem}@media(max-width:767.98px){.builder-page{height:auto}.builder-workspace{grid-template-columns:1fr}.builder-sidebar{max-height:42vh}.builder-canvas-area{min-height:60vh}.layout-canvas{width:100%}}
</style>
@endpush
@section('content')
<div class="builder-page">
    <div class="builder-header mb-3 d-flex justify-content-between align-items-start gap-3">
        <div><h1 class="page-title">{{ $template->nome ?: 'Template sem nome' }}</h1><p class="page-description mb-0">{{ $template->pagina ?: 'Página personalizada' }} · {{ $template->layout_pagina ?: 'Sem orientação' }} · {{ $template->largura }} × {{ $template->altura }} mm</p></div>
        <div class="d-flex gap-2"><button id="previewPdf" type="button" class="btn btn-outline-danger" title="Abrir preview em PDF"><i class="bi bi-file-earmark-pdf me-1"></i>Preview PDF</button><a href="{{ route('templates.index') }}" class="btn btn-outline-secondary">Voltar</a><button form="builderForm" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar</button></div>
    </div>
    @if(session('status'))<div class="alert alert-success py-2">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger py-2">Não foi possível salvar o layout. Revise os elementos.</div>@endif
    <form id="builderForm" method="POST" action="{{ route('templates.builder.save',$template) }}" class="builder-workspace">@csrf @method('PUT')<input type="hidden" id="layoutJson" name="layout_json">
        <aside class="card content-card builder-sidebar"><div class="card-body p-3">
            <div class="sidebar-sticky-title"><h2 class="h6 fw-bold mb-1">Variáveis</h2><p class="small text-muted mb-0">Clique para adicionar ao canvas.</p></div>
            <div id="variableList" class="d-grid gap-2 mt-2"></div>
            <div id="properties" class="properties-panel d-none"><div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h6 fw-bold mb-0">Elemento selecionado</h2><button type="button" id="removeElement" class="btn btn-sm btn-outline-danger" title="Remover elemento"><i class="bi bi-trash"></i></button></div>
                <div id="textProperty" class="mb-2"><label class="form-label small">Texto</label><textarea id="propText" rows="3" class="form-control form-control-sm"></textarea></div>
                <div class="row g-2"><div class="col-6"><label class="form-label small">X (mm)</label><input id="propX" type="number" min="0" step="0.1" class="form-control form-control-sm"></div><div class="col-6"><label class="form-label small">Y (mm)</label><input id="propY" type="number" min="0" step="0.1" class="form-control form-control-sm"></div><div class="col-6"><label class="form-label small">Largura (mm)</label><input id="propWidth" type="number" min="1" step="0.1" class="form-control form-control-sm"></div><div class="col-6"><label class="form-label small">Altura (mm)</label><input id="propHeight" type="number" min="1" step="0.1" class="form-control form-control-sm"></div></div>
                <div id="textStyles" class="row g-2 mt-0"><div class="col-6"><label class="form-label small">Cor</label><input id="propColor" type="color" class="form-control form-control-color w-100"></div><div class="col-6"><label class="form-label small">Alinhamento</label><select id="propAlign" class="form-select form-select-sm"><option value="esquerda">Esquerda</option><option value="centralizado">Centralizado</option><option value="direita">Direita</option><option value="justificado">Justificado</option></select></div></div>
            </div>
        </div></aside>
        <section class="builder-canvas-area"><div id="layoutCanvas" class="layout-canvas" style="--ratio:{{ max((int)$template->largura,1) / max((int)$template->altura,1) }};@if($template->backgroundUrl())background-image:url('{{ $template->backgroundUrl() }}')@endif">@unless($template->backgroundUrl())<div class="empty-background"><span><i class="bi bi-image me-1"></i>Template sem imagem de fundo</span></div>@endunless</div></section>
    </form>
</div>
@endsection
@push('scripts')
<script>
window.layoutBuilderConfig={width:@json(max((int)$template->largura,1)),height:@json(max((int)$template->altura,1)),variables:@json($variables),elements:@json($template->elementos_layout ?? []),previewUrl:@json(route('templates.builder.preview',$template))};
</script>
<script src="{{ asset('template-layout-builder.js') }}"></script>
@endpush
