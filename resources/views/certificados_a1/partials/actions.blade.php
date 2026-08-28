<div class="d-inline-flex gap-1 text-nowrap">
    @if($certificado->trashed())
        @if(in_array('certificados_a1.excluir_definitivamente', $permissions, true))<form method="POST" action="{{ route('certificados_a1.force-destroy', $certificado->id) }}" onsubmit="return confirm('Excluir este certificado A1 definitivamente?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-dark listagem-acao" title="Excluir definitivamente"><i class="bi bi-trash3-fill"></i></button></form>@endif
    @else
        @if(in_array('certificados_a1.visualizar', $permissions, true))<a href="{{ route('certificados_a1.show', $certificado) }}" class="btn btn-sm btn-outline-dark listagem-acao" title="Visualizar"><i class="bi bi-eye-fill"></i></a>@endif
        @if(in_array('certificados_a1.editar', $permissions, true))<a href="{{ route('certificados_a1.edit', $certificado) }}" class="btn btn-sm btn-outline-primary listagem-acao" title="Editar"><i class="bi bi-pencil-fill"></i></a>@endif
        @if(in_array('certificados_a1.excluir', $permissions, true))<form method="POST" action="{{ route('certificados_a1.destroy', $certificado) }}" onsubmit="return confirm('Excluir este certificado A1?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger listagem-acao" title="Excluir"><i class="bi bi-trash-fill"></i></button></form>@endif
    @endif
</div>
