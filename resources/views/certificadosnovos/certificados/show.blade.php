@extends('layouts.app')
@section('title', 'Visualizar certificado')
@section('content')
<h1 class="page-title mb-4">Visualizar certificado</h1>
<div class="card content-card"><div class="card-body p-4"><div class="row g-3">
@foreach([['ID',$item->id],['Emissão',$item->novoCertificado?->nome],['Template',$item->novoCertificado?->template?->nome],['Participante',$item->participante?->nome],['Evento',$item->novoCertificado?->evento?->nome ?: $item->novoCertificado?->atividade?->evento?->nome],['Atividade',$item->novoCertificado?->atividade?->nome],['Data do certificado',$item->novoCertificado?->data_emissao?->format('d/m/Y')],['Horas',data_get($item->snapshot_dados,'atividade.carga_horaria') ?: data_get($item->novoCertificado?->campos_personalizados,'carga_horaria')],['Status',$item->ativo?'Ativo':'Inativo'],['Gerado em',$item->gerado_em?->format('d/m/Y H:i')]] as [$label,$value])
<div class="col-md-6"><div class="form-label fw-semibold">{{ $label }}</div><div class="form-control bg-body-tertiary">{{ filled($value)?$value:'—' }}</div></div>
@endforeach
</div><div class="d-flex justify-content-end gap-2 mt-4">@if($item->arquivoExists())<a target="_blank" href="{{ route('certificadosnovos.pdf',$item) }}" class="btn btn-danger"><i class="bi bi-file-earmark-pdf me-1"></i>Visualizar PDF</a>@endif<a href="{{ route('certificadosnovos.index') }}" class="btn btn-outline-secondary">Voltar</a></div></div></div>
@endsection
