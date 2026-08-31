<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Certificado de {{ $item->participante?->nome }}</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
html,body{min-height:100%;margin:0;background:#eef1f5}.certificate-area{display:flex;justify-content:center;align-items:flex-start}.certificate-link{display:block;max-width:100%}.certificate-image{display:block;max-width:100%;height:auto;margin:auto;background:#fff;box-shadow:0 8px 30px #0002}
@page{size:A4 {{ $landscape ? 'landscape' : 'portrait' }};margin:0}
@media print{
 html,body{width:{{ $landscape ? '297mm' : '210mm' }};height:{{ $landscape ? '210mm' : '297mm' }};min-height:0;margin:0!important;padding:0!important;overflow:hidden!important;background:#fff!important}
 .page-info{display:none!important}
 .certificate-area{position:fixed;inset:0;width:100%;height:100%;margin:0!important;padding:0!important;display:flex!important;align-items:center!important;justify-content:center!important;overflow:hidden!important;background:#fff!important}
 .certificate-link{display:flex;width:100%;height:100%;align-items:center;justify-content:center;text-decoration:none}
 .certificate-image{display:block;width:100%;height:100%;max-width:100%;max-height:100%;object-fit:contain;box-shadow:none!important;break-inside:avoid;page-break-inside:avoid}
}
</style>
</head>
<body>
<header class="page-info bg-white border-bottom"><div class="container-fluid py-3"><div class="d-flex flex-wrap justify-content-between align-items-center gap-3"><div><h1 class="h4 mb-2">Certificado</h1><div><strong>Participante:</strong> {{ $item->participante?->nome ?: '—' }}</div><div><strong>Evento:</strong> {{ $item->novoCertificado?->evento?->nome ?: $item->novoCertificado?->atividade?->evento?->nome ?: '—' }}</div><div><strong>Atividade:</strong> {{ $item->novoCertificado?->atividade?->nome ?: '—' }}</div><div><strong>Horas:</strong> {{ data_get($item->snapshot_dados,'atividade.carga_horaria') ?: '—' }}</div></div><div class="text-md-end"><div class="d-flex flex-wrap justify-content-md-end gap-2">@if($item->arquivoExists())<a class="btn btn-outline-danger" href="{{ route('certificadosnovos.public.image-pdf-download',$item->codigo_img) }}">Baixar PDF</a>@endif<a class="btn btn-outline-primary" href="{{ route('certificadosnovos.public.image-download',$item->codigo_img) }}">Baixar imagem</a><button type="button" class="btn btn-primary" onclick="window.print()">Visualizar impressão</button></div><div class="small text-muted mt-2">A página já está configurada em A4 {{ $landscape ? 'paisagem' : 'retrato' }}, sem margens.<br>Se o navegador oferecer a opção, desative “Cabeçalhos e rodapés”.</div></div></div></div></header>
<main class="certificate-area p-4"><a class="certificate-link" href="{{ route('certificadosnovos.public.image-file',$item->codigo_img) }}" target="_blank" rel="noopener noreferrer"><img class="certificate-image" src="{{ route('certificadosnovos.public.image-file',$item->codigo_img) }}" alt="Certificado de {{ $item->participante?->nome }}"></a></main>
</body>
</html>
