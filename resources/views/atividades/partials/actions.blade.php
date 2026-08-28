<div class="d-inline-flex gap-1 text-nowrap">
@if($atividade->trashed())
    @if(in_array('atividades.restaurar', $permissions, true))<form method="POST" action="{{ route('atividades.restore', $atividade->id) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-success listagem-acao" title="Restaurar"><i class="bi bi-arrow-counterclockwise"></i></button></form>@endif
    @if(in_array('atividades.excluir_definitivamente', $permissions, true))<form method="POST" action="{{ route('atividades.force-destroy', $atividade->id) }}" onsubmit="return confirm('Excluir esta atividade definitivamente e apagar sua imagem?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-dark listagem-acao" title="Excluir definitivamente"><i class="bi bi-trash3-fill"></i></button></form>@endif
@else
    @if(in_array('atividades.visualizar', $permissions, true))<a href="{{ route('atividades.show', $atividade) }}" class="btn btn-sm btn-outline-dark listagem-acao" title="Visualizar"><i class="bi bi-eye-fill"></i></a>@endif
    @if(in_array('atividades.editar', $permissions, true))<a href="{{ route('atividades.edit', $atividade) }}" class="btn btn-sm btn-outline-primary listagem-acao" title="Editar"><i class="bi bi-pencil-fill"></i></a>@endif
    @if(in_array('atividades.editar', $permissions, true))<form method="POST" action="{{ route('atividades.status', $atividade) }}">@csrf @method('PATCH')<button class="btn btn-sm {{ $atividade->ativo ? 'btn-outline-warning' : 'btn-outline-success' }} listagem-acao" title="{{ $atividade->ativo ? 'Desativar' : 'Ativar' }}"><i class="bi {{ $atividade->ativo ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i></button></form>@endif
    @if(in_array('atividades.excluir', $permissions, true))<form method="POST" action="{{ route('atividades.destroy', $atividade) }}" onsubmit="return confirm('Excluir esta atividade?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger listagem-acao" title="Excluir"><i class="bi bi-trash-fill"></i></button></form>@endif
@endif
</div>
