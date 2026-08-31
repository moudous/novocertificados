@extends('layouts.app')
@section('title', $certificado->exists ? 'Editar certificado A1' : 'Novo certificado A1')

@section('content')
<div class="mb-4"><h1 class="page-title">{{ $certificado->exists ? 'Editar certificado A1' : 'Novo certificado A1' }}</h1><p class="page-description mb-0">Preencha os dados do certificado A1.</p></div>
<form method="POST" enctype="multipart/form-data" action="{{ $certificado->exists ? route('certificados_a1.update', $certificado) : route('certificados_a1.store') }}">
    @csrf @if($certificado->exists) @method('PUT') @endif
    <div class="card content-card"><div class="card-header"><h2 class="h5 fw-bold mb-0">Dados do certificado A1</h2></div><div class="card-body p-4">
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <div class="row g-3">
            <div class="col-12 col-md-8"><label for="nome" class="form-label">Nome</label><input id="nome" name="nome" class="form-control" maxlength="50" value="{{ old('nome', $certificado->nome) }}"></div>
            <div class="col-12 col-md-8"><label for="arquivo_certificado" class="form-label">Arquivo do certificado A1 {{ $certificado->certificateExists()?'':'*' }}</label><input id="arquivo_certificado" name="arquivo_certificado" type="file" class="form-control" accept=".pfx,.p12,application/x-pkcs12" @required(!$certificado->certificateExists())><div class="form-text">Arquivo PFX ou P12, com até 5 MB. O conteúdo será armazenado em área privada.</div>@if($certificado->certificateExists())<div class="small text-success mt-1"><i class="bi bi-shield-check me-1"></i>{{ $certificado->nome_arquivo_original ?: 'Certificado A1 armazenado' }}</div>@endif</div>
            <div class="col-12 col-md-4"><label for="senha_certificado" class="form-label">Senha do certificado {{ $certificado->certificateExists()?'':'*' }}</label><input id="senha_certificado" name="senha_certificado" type="password" class="form-control" maxlength="255" autocomplete="new-password" @required(!$certificado->certificateExists())><div class="form-text">@if($certificado->certificateExists())Deixe em branco para manter a senha atual.@else Necessária para validar e futuramente assinar os PDFs.@endif</div></div>
            @if($certificado->certificateExists())<div class="col-12"><div class="border rounded bg-light p-3 small"><div><strong>Titular:</strong> {{ $certificado->titular ?: 'Não identificado' }}</div><div><strong>Validade:</strong> {{ $certificado->valido_de?->format('d/m/Y H:i') ?: '—' }} até {{ $certificado->valido_ate?->format('d/m/Y H:i') ?: '—' }}</div><div><strong>Impressão digital SHA-256:</strong> <span class="font-monospace text-break">{{ $certificado->impressao_digital ?: '—' }}</span></div></div></div>@endif
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('certificados_a1.index') }}" class="btn btn-outline-secondary">Cancelar</a><button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar</button></div>
    </div></div>
</form>
@endsection
