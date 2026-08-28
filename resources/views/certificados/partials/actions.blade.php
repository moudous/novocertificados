<div class="d-inline-flex gap-1 text-nowrap">@if($certificado->trashed())
@if(in_array('certificados.restaurar',$permissions,true))<form method="POST" action="{{ route('certificados.restore',$certificado->id) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-success listagem-acao" title="Restaurar"><i class="bi bi-arrow-counterclockwise"></i></button></form>@endif
@if(in_array('certificados.excluir_definitivamente',$permissions,true))<form method="POST" action="{{ route('certificados.force-destroy',$certificado->id) }}" onsubmit="return confirm('Excluir este certificado definitivamente?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-dark listagem-acao" title="Excluir definitivamente"><i class="bi bi-trash3-fill"></i></button></form>@endif
@else
@if(in_array('certificados.visualizar',$permissions,true))<a href="{{ route('certificados.show',$certificado) }}" class="btn btn-sm btn-outline-dark listagem-acao" title="Visualizar"><i class="bi bi-eye-fill"></i></a>@endif
@if(in_array('certificados.editar',$permissions,true))<a href="{{ route('certificados.edit',$certificado) }}" class="btn btn-sm btn-outline-primary listagem-acao" title="Editar"><i class="bi bi-pencil-fill"></i></a>@endif
@if(in_array('certificados.excluir',$permissions,true))<form method="POST" action="{{ route('certificados.destroy',$certificado) }}" onsubmit="return confirm('Excluir este certificado?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger listagem-acao" title="Excluir"><i class="bi bi-trash-fill"></i></button></form>@endif
@endif</div>
