<div class="d-inline-flex gap-1 text-nowrap">
@if($rubrica->trashed())
    @if(in_array('rubricas_participantes.excluir_definitivamente',$permissions,true))<form method="POST" action="{{ route('rubricas_participantes.force-destroy',$rubrica->id) }}" onsubmit="return confirm('Excluir esta rubrica definitivamente?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-dark listagem-acao" title="Excluir definitivamente"><i class="bi bi-trash3-fill"></i></button></form>@endif
@else
    @if(in_array('rubricas_participantes.visualizar',$permissions,true))<a href="{{ route('rubricas_participantes.show',$rubrica) }}" class="btn btn-sm btn-outline-dark listagem-acao" title="Visualizar"><i class="bi bi-eye-fill"></i></a>@endif
    @if(in_array('rubricas_participantes.editar',$permissions,true))<a href="{{ route('rubricas_participantes.edit',$rubrica) }}" class="btn btn-sm btn-outline-primary listagem-acao" title="Editar"><i class="bi bi-pencil-fill"></i></a><form method="POST" action="{{ route('rubricas_participantes.status',$rubrica) }}">@csrf @method('PATCH')<button class="btn btn-sm {{ $rubrica->ativo?'btn-outline-warning':'btn-outline-success' }} listagem-acao" title="{{ $rubrica->ativo?'Desativar':'Ativar' }}"><i class="bi {{ $rubrica->ativo?'bi-toggle-on':'bi-toggle-off' }}"></i></button></form>@endif
    @if(in_array('rubricas_participantes.excluir',$permissions,true))<form method="POST" action="{{ route('rubricas_participantes.destroy',$rubrica) }}" onsubmit="return confirm('Excluir esta rubrica?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger listagem-acao" title="Excluir"><i class="bi bi-trash-fill"></i></button></form>@endif
@endif
</div>
