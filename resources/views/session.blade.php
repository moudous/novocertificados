@extends('layouts.app')
@section('title', 'Certificados')

@section('content')
<div class="page-container">
    <header class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-3"><div><h1 class="page-title">Certificados</h1><p class="page-description mb-0">Sessão criada com segurança pelo GI.</p></div><div class="d-flex flex-wrap gap-2">@if(in_array('certificados.listar', (array)data_get($context, 'permissoes', []), true))<a href="{{ route('certificados.index') }}" class="btn btn-primary"><i class="bi bi-award-fill me-2"></i>Certificados</a>@endif@if(in_array('atividades.listar', (array)data_get($context, 'permissoes', []), true))<a href="{{ route('atividades.index') }}" class="btn btn-outline-primary"><i class="bi bi-list-task me-2"></i>Atividades</a>@endif@if(in_array('eventos.listar', (array)data_get($context, 'permissoes', []), true))<a href="{{ route('eventos.index') }}" class="btn btn-outline-primary"><i class="bi bi-calendar-event-fill me-2"></i>Eventos</a>@endif@if(in_array('participantes.listar', (array)data_get($context, 'permissoes', []), true))<a href="{{ route('participantes.index') }}" class="btn btn-outline-primary"><i class="bi bi-person-vcard-fill me-2"></i>Participantes</a>@endif<a href="{{ route('pessoas.index') }}" class="btn btn-outline-primary"><i class="bi bi-people-fill me-2"></i>Pessoas</a></div></header>

    @if(session('manutencao'))
        <div class="alert alert-success d-flex align-items-start gap-3 shadow-sm" role="alert"><i class="bi bi-check-circle-fill fs-5"></i><div><strong class="d-block">Manutenção concluída</strong>{{ session('manutencao') }}</div></div>
    @endif
    @if(data_get($context, 'atualizacao_usuarios.realizada'))
        <div class="alert alert-success d-flex align-items-start gap-3 shadow-sm" role="alert"><i class="bi bi-people-fill fs-5"></i><div><strong class="d-block">Usuários atualizados</strong>O GI informou acréscimo de usuários e enviou {{ data_get($context, 'atualizacao_usuarios.total', 0) }} cadastro(s).</div></div>
    @endif
    <div id="executionContext" class="alert d-flex align-items-start gap-3 shadow-sm" role="status"></div>

    <section class="card content-card mb-4" aria-labelledby="profile-title">
        <div class="card-header"><h2 id="profile-title" class="h6 fw-bold mb-0">Contexto do perfil</h2></div>
        <div class="card-body p-4"><div class="row g-4">
            @foreach([
                ['Usuário', data_get($context, 'usuario.nome', 'Não informado'), data_get($context, 'usuario.email'), 'bi-person'],
                ['Sistema', data_get($context, 'sistema.nome', 'Não informado'), 'ID '.data_get($context, 'sistema.id', '—'), 'bi-window'],
                ['Perfil', data_get($context, 'perfil.nome', 'Não informado'), 'ID '.data_get($context, 'perfil.id', '—'), 'bi-person-badge'],
                ['Caminho solicitado', data_get($context, 'caminho', '/'), 'Emitido em '.data_get($context, 'emitido_em', '—'), 'bi-signpost'],
            ] as [$label, $value, $detail, $icon])
                <div class="col-12 col-md-6 col-xl-3"><div class="d-flex gap-3"><span class="context-icon"><i class="bi {{ $icon }}"></i></span><div><div class="small text-secondary mb-1">{{ $label }}</div><div class="fw-bold text-break">{{ $value }}</div><div class="small text-secondary text-break">{{ $detail }}</div></div></div></div>
            @endforeach
        </div></div>
    </section>

    <section class="card content-card mb-4" aria-labelledby="permissions-title">
        <div class="card-header"><h2 id="permissions-title" class="h6 fw-bold mb-1">Permissões entregues para este perfil</h2><p class="small text-secondary mb-0">Recursos disponibilizados pelo GI para a sessão atual.</p></div>
        <div class="card-body p-4 d-flex flex-wrap gap-2">
            @forelse((array) data_get($context, 'permissoes', []) as $permission)
                <code class="badge permission-badge rounded-pill px-3 py-2">{{ $permission }}</code>
            @empty
                <span class="text-secondary"><i class="bi bi-info-circle me-1"></i>Nenhuma permissão foi concedida.</span>
            @endforelse
        </div>
    </section>

    <section class="card content-card mb-4" aria-labelledby="actions-title">
        <div class="card-header"><h2 id="actions-title" class="h6 fw-bold mb-1">Ferramentas da integração</h2><p class="small text-secondary mb-0">Consulte o diretório do GI ou execute rotinas de manutenção.</p></div>
        <div class="card-body p-4">
            <div class="d-flex flex-wrap gap-2 mb-3" aria-label="Consultas ao diretório do GI">
                <button type="button" class="btn btn-primary" data-resource="perfis"><i class="bi bi-person-badge me-2"></i>Carregar perfis</button>
                <button type="button" class="btn btn-primary" data-resource="usuarios"><i class="bi bi-people me-2"></i>Carregar usuários</button>
                <button type="button" class="btn btn-primary" data-resource="grupos"><i class="bi bi-collection me-2"></i>Carregar grupos</button>
            </div>
            <div class="d-flex flex-wrap gap-2" aria-label="Manutenção do Laravel">
                <form method="POST" action="{{ route('manutencao.executar', 'optimize-clear') }}">@csrf<button class="btn btn-outline-secondary" type="submit"><i class="bi bi-arrow-repeat me-2"></i>php artisan optimize:clear</button></form>
                <form method="POST" action="{{ route('manutencao.executar', 'config-cache') }}">@csrf<button class="btn btn-outline-secondary" type="submit"><i class="bi bi-gear me-2"></i>php artisan config:cache</button></form>
            </div>
            <pre id="result" class="json-result mt-4" aria-live="polite" hidden></pre>
        </div>
    </section>

    <section class="card content-card" aria-labelledby="json-title">
        <div class="card-header d-flex justify-content-between align-items-center gap-3"><div><h2 id="json-title" class="h6 fw-bold mb-1">Contexto JSON recebido</h2><p class="small text-secondary mb-0">Dados visíveis da sessão, sem o token de acesso.</p></div><i class="bi bi-braces fs-4 text-secondary"></i></div>
        <div class="card-body p-4"><pre class="json-result">{{ json_encode($context, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre></div>
    </section>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const contextBlock = document.getElementById('executionContext');
    const isInsideGi = window.self !== window.top;
    contextBlock.classList.add(isInsideGi ? 'alert-success' : 'alert-warning');
    contextBlock.innerHTML = isInsideGi
        ? '<i class="bi bi-shield-check fs-5"></i><div><strong class="d-block">Executando dentro do GI</strong>Esta página está sendo exibida no ambiente integrado do sistema GI.</div>'
        : '<i class="bi bi-exclamation-triangle-fill fs-5"></i><div><strong class="d-block">Executando fora do GI</strong>Esta página foi aberta diretamente, fora do ambiente integrado do sistema GI.</div>';

    const buttons = document.querySelectorAll('[data-resource]');
    buttons.forEach(button => button.addEventListener('click', async () => {
        const result = document.getElementById('result');
        result.hidden = false;
        result.textContent = 'Carregando...';
        buttons.forEach(item => item.disabled = true);
        try {
            const response = await fetch(@json(url('/gi')) + '/' + encodeURIComponent(button.dataset.resource), {headers: {'Accept': 'application/json'}});
            const payload = await response.json().catch(() => ({message: 'O GI retornou uma resposta inválida.'}));
            if (!response.ok) throw new Error(payload.message || `Falha na consulta (HTTP ${response.status}).`);
            result.textContent = JSON.stringify(payload, null, 2);
        } catch (error) {
            result.textContent = JSON.stringify({message: error.message}, null, 2);
        } finally {
            buttons.forEach(item => item.disabled = false);
        }
    }));
});
</script>
@endpush
