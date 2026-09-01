@extends('layouts.app')
@section('title', 'Unificação #'.$unificacao->id)
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4"><div><h1 class="page-title">Unificação #{{ $unificacao->id }}</h1><p class="page-description mb-0">Detalhes e restauração da unificação de participantes.</p></div><a href="{{ route('desfazerunificacao.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Voltar</a></div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if(session('undo_conflicts'))
<div class="card border-warning mb-4"><div class="card-header bg-warning-subtle fw-bold">Intervenção manual necessária</div><div class="card-body"><p>Os participantes abaixo usam um ID ou e-mail necessário para a restauração. Revise e corrija esses cadastros manualmente e depois volte para executar “Desfazer unificação”. Nenhum dado desta unificação foi alterado nesta tentativa.</p><div class="table-responsive"><table class="table table-bordered align-middle mb-0"><thead><tr><th>Conflito</th><th>Participante a restaurar</th><th>Participante existente</th></tr></thead><tbody>@foreach(session('undo_conflicts') as $conflict)<tr><td>{{ $conflict['tipo'] }}</td><td>ID {{ data_get($conflict,'original.id') }}<br>{{ data_get($conflict,'original.nome') }}<br><span class="text-muted">{{ data_get($conflict,'original.email') }}</span></td><td>ID {{ data_get($conflict,'existente.id') }}<br>{{ data_get($conflict,'existente.nome') }}<br><span class="text-muted">{{ data_get($conflict,'existente.email') }}</span></td></tr>@endforeach</tbody></table></div></div></div>
@endif
<div class="card content-card mb-4"><div class="card-body p-4"><div class="row g-3">
<div class="col-md-4"><div class="form-label fw-semibold">Participante da unificação</div><div class="form-control bg-body-tertiary">ID {{ $unificacao->participante_novo_id }} · {{ $unificacao->participante_novo_nome ?: '—' }}</div></div>
<div class="col-md-4"><div class="form-label fw-semibold">Realizada por</div><div class="form-control bg-body-tertiary">{{ $unificacao->usuario_nome ?: ($unificacao->usuario_id ? 'Usuário #'.$unificacao->usuario_id : '—') }}</div></div>
<div class="col-md-2"><div class="form-label fw-semibold">Data e hora</div><div class="form-control bg-body-tertiary">{{ $unificacao->criado_em?->format('d/m/Y H:i:s') ?: '—' }}</div></div>
<div class="col-md-2"><div class="form-label fw-semibold">Status</div><div class="form-control bg-body-tertiary">{{ $unificacao->status === 'desfeita' ? 'Desfeita' : 'Realizada' }}</div></div>
</div></div></div>
<div class="card content-card mb-4"><div class="card-header"><h2 class="h5 fw-bold mb-0">Participantes excluídos</h2></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-bordered align-middle mb-0"><thead><tr><th>ID original</th><th>Nome</th><th>E-mail</th></tr></thead><tbody>@forelse($unificacao->participantes_excluidos as $participant)<tr><td>{{ $participant['id'] }}</td><td>{{ $participant['nome'] ?: '—' }}</td><td>{{ $participant['email'] ?: '—' }}</td></tr>@empty<tr><td colspan="3" class="text-center text-muted">Nenhum participante excluído.</td></tr>@endforelse</tbody></table></div></div></div>
@if($unificacao->status === 'desfeita')
<div class="alert alert-secondary"><strong>Unificação desfeita.</strong> Esta ação foi executada por {{ $unificacao->desfeito_por_nome ?: ($unificacao->desfeito_por ? 'Usuário #'.$unificacao->desfeito_por : '—') }}, em {{ $unificacao->desfeito_em?->format('d/m/Y H:i:s') ?: '—' }}, e não pode ser repetida.</div>
@elseif(in_array('desfazerunificacao.desfazer',$permissions,true))
<div class="card border-danger"><div class="card-body"><h2 class="h5 text-danger">Desfazer esta unificação</h2><p>Os participantes excluídos serão recriados com seus IDs originais e os certificados legados, novos e demais vínculos retornarão ao estado anterior.</p><form method="POST" action="{{ route('desfazerunificacao.undo',$unificacao) }}" onsubmit="return confirm('Deseja desfazer esta unificação? O sistema restaurará os registros ao estado anterior.')">@csrf<button class="btn btn-danger"><i class="bi bi-arrow-counterclockwise me-1"></i>Desfazer unificação</button></form></div></div>
@endif
@endsection
