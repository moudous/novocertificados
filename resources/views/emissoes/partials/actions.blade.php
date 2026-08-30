<div class="d-inline-flex gap-1">
    @if($certificado->trashed())
        @if(in_array('emissoes.excluir_definitivamente', $permissions, true))
            @if((int) ($certificado->participantes_count ?? 0) > 0)
                <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary listagem-acao"
                    title="Não é possível excluir definitivamente uma emissão que possui participantes"
                    disabled
                >
                    <i class="bi bi-trash3"></i>
                </button>
            @else
                <form
                    method="POST"
                    action="{{ route('emissoes.force-destroy', $certificado->id) }}"
                    onsubmit="return confirm('Excluir esta emissão definitivamente? Esta ação não pode ser desfeita.')"
                >
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-outline-dark listagem-acao" title="Excluir definitivamente">
                        <i class="bi bi-trash3"></i>
                    </button>
                </form>
            @endif
        @endif
    @else
        @if(in_array('emissoes.visualizar', $permissions, true))
            <a href="{{ route('emissoes.show', $certificado) }}" class="btn btn-sm btn-outline-dark listagem-acao" title="Visualizar">
                <i class="bi bi-eye"></i>
            </a>
        @endif
        @if(in_array('emissoes.participantes', $permissions, true))
            <a href="{{ route('emissoes.participantes', $certificado) }}" class="btn btn-sm btn-outline-success listagem-acao" title="Participantes">
                <i class="bi bi-people"></i>
            </a>
        @endif
        @if(in_array('emissoes.editar', $permissions, true))
            <a href="{{ route('emissoes.edit', $certificado) }}" class="btn btn-sm btn-outline-primary listagem-acao" title="Editar">
                <i class="bi bi-pencil"></i>
            </a>
        @endif
        @if(in_array('emissoes.ativar_desativar', $permissions, true))
            <form method="POST" action="{{ route('emissoes.status', $certificado) }}">
                @csrf
                @method('PATCH')
                <button class="btn btn-sm btn-outline-warning listagem-acao" title="Alterar status">
                    <i class="bi bi-toggle-on"></i>
                </button>
            </form>
        @endif
        @if(in_array('emissoes.excluir', $permissions, true))
            <form method="POST" action="{{ route('emissoes.destroy', $certificado) }}">
                @csrf
                @method('DELETE')
                <button class="btn btn-sm btn-outline-danger listagem-acao" title="Excluir">
                    <i class="bi bi-trash"></i>
                </button>
            </form>
        @endif
    @endif
</div>
