<div class="d-inline-flex gap-1">
    @if($item->novoCertificado?->certificadoAntigo && filled($item->novoCertificado->certificadoAntigo->arquivo) && ! $item->novoCertificado->certificadoAntigo->trashed() && in_array('certificados.visualizar', $permissions, true))
        <a href="{{ route('certificados.legacy', $item->novoCertificado->certificadoAntigo->arquivo) }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-info listagem-acao" title="Abrir certificado antigo" aria-label="Abrir certificado antigo"><i class="bi bi-box-arrow-up-right"></i></a>
    @endif
    @if(in_array('certificadosnovos.ativar_desativar', $permissions, true))
        <form method="POST" action="{{ route('certificadosnovos.status', $item) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-warning listagem-acao" title="{{ $item->ativo ? 'Desativar' : 'Ativar' }}"><i class="bi {{ $item->ativo ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i></button></form>
    @endif
    @if(in_array('certificadosnovos.gerar_pdf', $permissions, true))
        <form method="POST" action="{{ route('certificadosnovos.generate', $item) }}">@csrf<button class="btn btn-sm btn-outline-danger listagem-acao" title="Gerar PDF"><i class="bi bi-file-earmark-pdf"></i></button></form>
    @endif
    @if(in_array('certificadosnovos.visualizar', $permissions, true))
        <a href="{{ route('certificadosnovos.show', $item) }}" class="btn btn-sm btn-outline-dark listagem-acao" title="Visualizar"><i class="bi bi-eye"></i></a>
    @endif
</div>
