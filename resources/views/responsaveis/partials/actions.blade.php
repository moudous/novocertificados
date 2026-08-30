<div class="d-inline-flex gap-1 text-nowrap">
@if($responsavel->trashed())
    @if(in_array('responsaveis.restaurar',$permissions,true))<form method="POST" action="{{ route('responsaveis.restore',$responsavel->id) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-success listagem-acao" title="Restaurar"><i class="bi bi-arrow-counterclockwise"></i></button></form>@endif
    @if(in_array('responsaveis.excluir_definitivamente',$permissions,true))<form method="POST" action="{{ route('responsaveis.force-destroy',$responsavel->id) }}" onsubmit="return confirm('Excluir este responsável definitivamente?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-dark listagem-acao" title="Excluir definitivamente"><i class="bi bi-trash3-fill"></i></button></form>@endif
@else
    @if(in_array('responsaveis.visualizar',$permissions,true))<a href="{{ route('responsaveis.show',$responsavel) }}" class="btn btn-sm btn-outline-dark listagem-acao" title="Visualizar"><i class="bi bi-eye-fill"></i></a>@endif
    @if(in_array('responsaveis.editar',$permissions,true))<a href="{{ route('responsaveis.edit',$responsavel) }}" class="btn btn-sm btn-outline-primary listagem-acao" title="Editar"><i class="bi bi-pencil-fill"></i></a><form method="POST" action="{{ route('responsaveis.status',$responsavel) }}">@csrf @method('PATCH')<button class="btn btn-sm {{ $responsavel->ativo?'btn-outline-warning':'btn-outline-success' }} listagem-acao" title="{{ $responsavel->ativo?'Desativar':'Ativar' }}"><i class="bi {{ $responsavel->ativo?'bi-toggle-on':'bi-toggle-off' }}"></i></button></form>@endif
    @if(in_array('responsaveis.excluir',$permissions,true))<form method="POST" action="{{ route('responsaveis.destroy',$responsavel) }}" onsubmit="return confirm('Excluir este responsável?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger listagem-acao" title="Excluir"><i class="bi bi-trash-fill"></i></button></form>@endif
@endif
</div>
