@extends('layouts.app')
@section('title', $certificado->exists ? 'Editar certificado A1' : 'Novo certificado A1')

@section('content')
<div class="mb-4"><h1 class="page-title">{{ $certificado->exists ? 'Editar certificado A1' : 'Novo certificado A1' }}</h1><p class="page-description mb-0">Preencha os dados do certificado A1.</p></div>
<div class="accordion mb-4" id="certificateHelp">
    <div class="accordion-item">
        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#officialCertificateHelp" aria-expanded="false" aria-controls="officialCertificateHelp"><i class="bi bi-building-check me-2"></i>Como adquirir um certificado A1 oficial</button></h2>
        <div id="officialCertificateHelp" class="accordion-collapse collapse" data-bs-parent="#certificateHelp"><div class="accordion-body">
            <p>Adquira um certificado digital do tipo <strong>A1 para pessoa jurídica (e-CNPJ)</strong> ou outro A1 compatível diretamente com uma Autoridade Certificadora credenciada. A certificadora fará a validação da empresa e de seu representante e fornecerá instruções para emissão e download.</p>
            <ol class="mb-3"><li>Escolha uma Autoridade Certificadora e solicite um certificado A1 adequado à empresa.</li><li>Conclua a validação de identidade solicitada pela certificadora.</li><li>Na emissão, crie uma senha forte e guarde-a em local seguro.</li><li>Exporte ou baixe o certificado no padrão <strong>PKCS#12</strong>, com extensão <code>.pfx</code> ou <code>.p12</code>.</li><li>Envie o arquivo e informe a senha neste formulário.</li></ol>
            <div class="alert alert-info mb-0"><strong>Segurança:</strong> o arquivo é mantido em área privada. A senha não é armazenada em texto legível; somente seu valor criptografado é persistido, usando a chave de criptografia da aplicação. Ela é descriptografada apenas quando necessária para assinar um PDF.</div>
        </div></div>
    </div>
    <div class="accordion-item">
        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#testCertificateHelp" aria-expanded="false" aria-controls="testCertificateHelp"><i class="bi bi-terminal me-2"></i>Como gerar um certificado de teste no Ubuntu Linux</button></h2>
        <div id="testCertificateHelp" class="accordion-collapse collapse" data-bs-parent="#certificateHelp"><div class="accordion-body">
            <div class="alert alert-warning">Use este certificado somente em desenvolvimento e testes. Por ser autoassinado, ele não terá a confiança pública de um certificado emitido por uma Autoridade Certificadora.</div>
            <ol>
                <li class="mb-3">Instale o OpenSSL:<pre class="bg-dark text-light rounded p-3 mt-2 mb-0"><code>sudo apt update
sudo apt install openssl</code></pre></li>
                <li class="mb-3">Crie uma pasta protegida para os arquivos:<pre class="bg-dark text-light rounded p-3 mt-2 mb-0"><code>mkdir -p certificado-teste
cd certificado-teste
chmod 700 .</code></pre></li>
                <li class="mb-3">Gere a chave privada e o certificado autoassinado, válido por 365 dias:<pre class="bg-dark text-light rounded p-3 mt-2 mb-0"><code>openssl req -x509 -newkey rsa:2048 -sha256 -days 365 -nodes \
  -keyout private.key \
  -out certificate.crt \
  -subj "/C=BR/ST=Seu Estado/L=Sua Cidade/O=Empresa Teste/CN=Empresa Teste"</code></pre></li>
                <li class="mb-3">Converta para o formato PKCS#12 aceito pelo sistema. O comando solicitará a senha de exportação:<pre class="bg-dark text-light rounded p-3 mt-2 mb-0"><code>openssl pkcs12 -export \
  -out certificado-teste.pfx \
  -inkey private.key \
  -in certificate.crt \
  -name "Certificado A1 de teste"</code></pre></li>
                <li class="mb-3">Proteja os arquivos locais:<pre class="bg-dark text-light rounded p-3 mt-2 mb-0"><code>chmod 600 private.key certificate.crt certificado-teste.pfx</code></pre></li>
                <li>Envie <code>certificado-teste.pfx</code> neste formulário e informe a mesma senha criada na exportação. Se preferir a extensão <code>.p12</code>, use esse nome no parâmetro <code>-out</code>.</li>
            </ol>
        </div></div>
    </div>
</div>
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
