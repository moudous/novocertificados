<div class="d-inline-flex gap-1 text-nowrap">
@if($template->trashed())
    @if(in_array('template.excluir_definitivamente',$permissions,true))<form method="POST" action="{{ route('templates.force-destroy',$template->id) }}" onsubmit="return confirm('Excluir este template definitivamente?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-dark listagem-acao" title="Excluir definitivamente"><i class="bi bi-trash3-fill"></i></button></form>@endif
@else
    @if(in_array('template.visualizar',$permissions,true))<a href="{{ route('templates.show',$template) }}" class="btn btn-sm btn-outline-dark listagem-acao" title="Visualizar"><i class="bi bi-eye-fill"></i></a>@endif
    @if(in_array('emissoes.listar',$permissions,true))<a href="{{ route('emissoes.index',['template_id'=>$template->id]) }}" class="btn btn-sm btn-outline-info listagem-acao" title="Certificados deste template"><i class="bi bi-award"></i></a>@endif
    @if(in_array('templates.duplicar',$permissions,true) || (in_array('templates._duplicar_proprio',$permissions,true) && $template->criado_por === (int)session('gi_context.usuario.id')))<form method="POST" action="{{ route('templates.duplicate',$template) }}">@csrf<button class="btn btn-sm btn-outline-secondary listagem-acao" title="Duplicar template"><i class="bi bi-copy"></i></button></form>@endif
    @if(in_array('template.editar',$permissions,true))
        <a href="{{ route('templates.builder',$template) }}" class="btn btn-sm btn-outline-success listagem-acao" title="Construtor de layouts"><i class="bi bi-bounding-box-circles"></i></a>
        <a href="{{ route('templates.edit',$template) }}" class="btn btn-sm btn-outline-primary listagem-acao" title="Editar"><i class="bi bi-pencil-fill"></i></a>
        <form method="POST" action="{{ route('templates.status',$template) }}">@csrf @method('PATCH')<button class="btn btn-sm {{ $template->ativo?'btn-outline-warning':'btn-outline-success' }} listagem-acao" title="{{ $template->ativo?'Desativar':'Ativar' }}"><i class="bi {{ $template->ativo?'bi-toggle-on':'bi-toggle-off' }}"></i></button></form>
    @endif
    @if(in_array('template.excluir',$permissions,true))<form method="POST" action="{{ route('templates.destroy',$template) }}" onsubmit="return confirm('Excluir este template?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger listagem-acao" title="Excluir"><i class="bi bi-trash-fill"></i></button></form>@endif
@endif
</div>
