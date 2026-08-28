<div class="d-inline-flex gap-1 text-nowrap">
    @php($params = ['id' => $participante->id, 'nome' => $participante->nome])
    @if($participante->trashed())
        @if(in_array('participantes.restaurar', $permissions, true))
            <form method="POST" action="{{ route('participantes.restore', $params) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-success listagem-acao" title="Restaurar"><i class="bi bi-arrow-counterclockwise"></i></button></form>
        @endif
        @if(in_array('participantes.excluir_definitivamente', $permissions, true))
            <form method="POST" action="{{ route('participantes.force-destroy', $params) }}" onsubmit="return confirm('Excluir este participante definitivamente?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-dark listagem-acao" title="Excluir definitivamente"><i class="bi bi-trash3-fill"></i></button></form>
        @endif
    @else
        @if(in_array('participantes.visualizar', $permissions, true))<a href="{{ route('participantes.show', $params) }}" class="btn btn-sm btn-outline-dark listagem-acao" title="Visualizar"><i class="bi bi-eye-fill"></i></a>@endif
        @if(in_array('participantes.editar', $permissions, true))<a href="{{ route('participantes.edit', $params) }}" class="btn btn-sm btn-outline-primary listagem-acao" title="Editar"><i class="bi bi-pencil-fill"></i></a>@endif
        @if(in_array('participantes.excluir', $permissions, true))
            <form method="POST" action="{{ route('participantes.destroy', $params) }}" onsubmit="return confirm('Excluir este participante?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger listagem-acao" title="Excluir"><i class="bi bi-trash-fill"></i></button></form>
        @endif
    @endif
</div>
