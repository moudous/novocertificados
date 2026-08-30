<div class="d-flex gap-1 text-nowrap">
@if($imagem->trashed())
 @if(in_array('biblioteca_imagens.restaurar',$permissions,true))<form method="POST" action="{{ route('biblioteca_imagens.restore',$imagem->id) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-success" title="Restaurar"><i class="bi bi-arrow-counterclockwise"></i></button></form>@endif
 @if(in_array('biblioteca_imagens.excluir_definitivamente',$permissions,true))<button type="button" class="btn btn-sm btn-outline-dark" title="Excluir definitivamente" data-bs-toggle="modal" data-bs-target="#forceDeleteImage{{ $imagem->id }}"><i class="bi bi-trash3-fill"></i></button>@endif
@else
 @if(in_array('biblioteca_imagens.editar',$permissions,true))<a class="btn btn-sm btn-outline-primary" title="Editar" href="{{ route('biblioteca_imagens.edit',$imagem) }}"><i class="bi bi-pencil"></i></a><form method="POST" action="{{ route('biblioteca_imagens.status',$imagem) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-warning" title="Alterar status"><i class="bi bi-toggle-on"></i></button></form>@endif
 @if(in_array('biblioteca_imagens.excluir',$permissions,true))<form method="POST" action="{{ route('biblioteca_imagens.destroy',$imagem) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" title="Excluir"><i class="bi bi-trash"></i></button></form>@endif
@endif
</div>
@if($imagem->trashed() && in_array('biblioteca_imagens.excluir_definitivamente',$permissions,true))
<div class="modal fade text-start" id="forceDeleteImage{{ $imagem->id }}" tabindex="-1" aria-labelledby="forceDeleteImageLabel{{ $imagem->id }}" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
 <div class="modal-header"><h2 class="modal-title fs-5" id="forceDeleteImageLabel{{ $imagem->id }}">Excluir imagem definitivamente</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
 <div class="modal-body">@if($usedTemplates->isNotEmpty())<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>Não é possível excluir esta imagem definitivamente porque ela está sendo utilizada.</div><div class="fw-semibold mb-2">Templates que utilizam a imagem:</div><ul class="mb-0">@foreach($usedTemplates as $templateName)<li>{{ $templateName }}</li>@endforeach</ul>@else<div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle-fill me-1"></i>Esta operação não pode ser desfeita. O registro e o arquivo físico <strong>{{ $imagem->arquivo }}</strong> serão removidos definitivamente.</div>@endif</div>
 <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>@if($usedTemplates->isEmpty())<form method="POST" action="{{ route('biblioteca_imagens.force-destroy',$imagem->id) }}">@csrf @method('DELETE')<button class="btn btn-danger"><i class="bi bi-trash3-fill me-1"></i>Excluir definitivamente</button></form>@endif</div>
</div></div></div>
@endif
