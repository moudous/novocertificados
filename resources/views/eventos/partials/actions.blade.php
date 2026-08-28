<div class="d-inline-flex gap-1 text-nowrap">
    @if($evento->trashed())
        @if(in_array('eventos.restaurar', $permissions, true))<form method="POST" action="{{ route('eventos.restore', $evento->id) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-success listagem-acao" title="Restaurar"><i class="bi bi-arrow-counterclockwise"></i></button></form>@endif
        @if(in_array('eventos.excluir_definitivamente', $permissions, true))<form method="POST" action="{{ route('eventos.force-destroy', $evento->id) }}" onsubmit="return confirm('Excluir este evento definitivamente?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-dark listagem-acao" title="Excluir definitivamente"><i class="bi bi-trash3-fill"></i></button></form>@endif
    @else
        @if(in_array('eventos.visualizar', $permissions, true))<a href="{{ route('eventos.show', $evento) }}" class="btn btn-sm btn-outline-dark listagem-acao" title="Visualizar"><i class="bi bi-eye-fill"></i></a>@endif
        @if(in_array('eventos.editar', $permissions, true))<a href="{{ route('eventos.edit', $evento) }}" class="btn btn-sm btn-outline-primary listagem-acao" title="Editar"><i class="bi bi-pencil-fill"></i></a>@endif
        @if(in_array('eventos.editar', $permissions, true))<form method="POST" action="{{ route('eventos.status', $evento) }}">@csrf @method('PATCH')<button class="btn btn-sm {{ $evento->ativo ? 'btn-outline-warning' : 'btn-outline-success' }} listagem-acao" title="{{ $evento->ativo ? 'Desativar' : 'Ativar' }}"><i class="bi {{ $evento->ativo ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i></button></form>@endif
        @if(in_array('eventos.excluir', $permissions, true))<form method="POST" action="{{ route('eventos.destroy', $evento) }}" onsubmit="return confirm('Excluir este evento?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger listagem-acao" title="Excluir"><i class="bi bi-trash-fill"></i></button></form>@endif
    @endif
</div>
