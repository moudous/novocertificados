<div class="d-inline-flex gap-1 text-nowrap">
@if($variavel->trashed())
    @if(in_array('variaveis.excluir_definitivamente',$permissions,true))<form method="POST" action="{{ route('variaveis.force-destroy',$variavel->id) }}" onsubmit="return confirm('Excluir esta variável definitivamente?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-dark listagem-acao" title="Excluir definitivamente"><i class="bi bi-trash3-fill"></i></button></form>@endif
@else
    @if(in_array('variaveis.visualizar',$permissions,true))<a href="{{ route('variaveis.show',$variavel) }}" class="btn btn-sm btn-outline-dark listagem-acao" title="Visualizar"><i class="bi bi-eye-fill"></i></a>@endif
    @if(in_array('variaveis.editar',$permissions,true))<a href="{{ route('variaveis.edit',$variavel) }}" class="btn btn-sm btn-outline-primary listagem-acao" title="Editar"><i class="bi bi-pencil-fill"></i></a><form method="POST" action="{{ route('variaveis.status',$variavel) }}">@csrf @method('PATCH')<button class="btn btn-sm {{ $variavel->ativo?'btn-outline-warning':'btn-outline-success' }} listagem-acao" title="{{ $variavel->ativo?'Desativar':'Ativar' }}"><i class="bi {{ $variavel->ativo?'bi-toggle-on':'bi-toggle-off' }}"></i></button></form>@endif
    @if(in_array('variaveis.excluir',$permissions,true))<form method="POST" action="{{ route('variaveis.destroy',$variavel) }}" onsubmit="return confirm('Excluir esta variável?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger listagem-acao" title="Excluir"><i class="bi bi-trash-fill"></i></button></form>@endif
@endif
</div>
