@extends('layouts.app')
@section('title', 'Pessoas')

@push('styles')
<link href="https://cdn.datatables.net/2.3.2/css/dataTables.bootstrap5.min.css" rel="stylesheet">
@endpush

@section('content')
<div class="w-100">
    <div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div><h1 class="page-title">Cadastro de pessoas</h1><p class="page-description mb-0">Consulte as pessoas sincronizadas automaticamente com o GI.</p></div>
        <button id="importPeople" type="button" class="btn btn-primary">
            <i class="bi bi-cloud-arrow-down-fill me-2"></i><span>Importar pessoas</span>
        </button>
    </div>

    <div id="importFeedback" class="alert alert-dismissible fade show d-none" role="alert">
        <span></span><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>

    <div class="card content-card">
        <div class="card-header"><h5>Pessoas cadastradas</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="pessoasTable" class="table table-hover align-middle w-100 mb-0">
                    <thead><tr><th>ID GI</th><th>Nome</th><th>E-mail</th><th>Perfil</th><th>ID perfil</th><th>Status</th><th>Última sincronização</th><th class="text-center" data-dt-order="disable">Ações</th></tr></thead>
                    <tbody>
                    @foreach($pessoas as $pessoa)
                        <tr>
                            <td class="text-nowrap">{{ $pessoa->id }}</td>
                            <td>{{ $pessoa->nome }}</td>
                            <td>{{ $pessoa->email }}</td>
                            <td>{{ $pessoa->perfil ?? '—' }}</td>
                            <td>{{ $pessoa->perfil_id ?? '—' }}</td>
                            <td><span class="badge {{ $pessoa->ativo ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $pessoa->ativo ? 'Ativa' : 'Inativa' }}</span></td>
                            <td class="text-nowrap" data-order="{{ $pessoa->updated_at?->timestamp ?? 0 }}">{{ $pessoa->updated_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="text-center"><a href="{{ route('pessoas.show', $pessoa) }}" class="btn btn-sm btn-outline-dark listagem-acao" title="Visualizar pessoa" aria-label="Visualizar {{ $pessoa->nome }}"><i class="bi bi-eye-fill"></i></a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.3.2/js/dataTables.bootstrap5.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const table = new DataTable('#pessoasTable', {
        order: [[0, 'desc']],
        language: {
            emptyTable: 'Nenhuma pessoa cadastrada.', info: 'Exibindo _START_ a _END_ de _TOTAL_ pessoas',
            infoEmpty: 'Nenhuma pessoa encontrada', lengthMenu: 'Exibir _MENU_ registros', search: 'Pesquisar:',
            zeroRecords: 'Nenhuma pessoa encontrada.', paginate: {first: 'Primeira', last: 'Última', next: 'Próxima', previous: 'Anterior'}
        }
    });

    const button = document.getElementById('importPeople');
    const buttonLabel = button.querySelector('span');
    const feedback = document.getElementById('importFeedback');

    button.addEventListener('click', async () => {
        button.disabled = true;
        buttonLabel.textContent = 'Importando...';
        feedback.classList.add('d-none');

        try {
            const response = await fetch(@json(route('pessoas.import')), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': @json(csrf_token()),
                },
            });
            const payload = await response.json().catch(() => ({message: 'O servidor retornou uma resposta inválida.'}));
            if (!response.ok) throw new Error(payload.message || `Falha na importação (HTTP ${response.status}).`);

            window.location.reload();
        } catch (error) {
            feedback.querySelector('span').textContent = error.message;
            feedback.classList.remove('d-none', 'alert-success');
            feedback.classList.add('alert-danger');
        } finally {
            button.disabled = false;
            buttonLabel.textContent = 'Importar pessoas';
        }
    });
});
</script>
@endpush
