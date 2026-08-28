<div class="d-inline-flex gap-1 text-nowrap">
@if($registro->trashed())
    @if(in_array('participantes_teste.excluir_definitivamente',$permissions,true))<form method="POST" action="{{ route('participantes_teste.force-destroy',$registro->id) }}" onsubmit="return confirm('Excluir este participante de teste definitivamente?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-dark listagem-acao" title="Excluir definitivamente"><i class="bi bi-trash3-fill"></i></button></form>@endif
@else
    @if(in_array('participantes_teste.visualizar',$permissions,true))<a href="{{ route('participantes_teste.show',$registro) }}" class="btn btn-sm btn-outline-dark listagem-acao" title="Visualizar"><i class="bi bi-eye-fill"></i></a>@endif
    @if(in_array('participantes_teste.editar',$permissions,true))<a href="{{ route('participantes_teste.edit',$registro) }}" class="btn btn-sm btn-outline-primary listagem-acao" title="Editar"><i class="bi bi-pencil-fill"></i></a>@endif
    @if(in_array('participantes_teste.excluir',$permissions,true))<form method="POST" action="{{ route('participantes_teste.destroy',$registro) }}" onsubmit="return confirm('Excluir este participante de teste?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger listagem-acao" title="Excluir"><i class="bi bi-trash-fill"></i></button></form>@endif
@endif
</div>
