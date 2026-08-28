@extends('layouts.app')
@section('title', 'Participantes')

@push('styles')
<link href="https://cdn.datatables.net/2.3.2/css/dataTables.bootstrap5.min.css" rel="stylesheet">
@endpush

@section('content')
<div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div><h1 class="page-title">Participantes</h1><p class="page-description mb-0">Gerencie os participantes cadastrados.</p></div>
    @if(in_array('participantes.criar', $permissions, true))
        <a href="{{ route('participantes.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Novo participante</a>
    @endif
</div>

@if(session('status'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('status') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card content-card">
    <div class="card-header"><h2 class="h5 fw-bold mb-0">Participantes cadastrados</h2></div>
    <div class="card-body p-0"><div class="table-responsive">
        <table id="participantesTable" class="table table-hover align-middle w-100 mb-0">
            <thead><tr><th class="text-center" data-dt-order="disable"><button id="clearParticipantSelection" type="button" class="btn btn-sm btn-link text-danger p-0" title="limpar seleção" aria-label="Limpar seleção"><i class="bi bi-trash-fill"></i></button></th><th>ID</th><th>Nome</th><th>E-mail</th><th>CPF</th><th>Sexo</th><th>Grupo</th><th>Status</th><th>Criado em</th><th>Atualizado em</th><th class="text-center" data-dt-order="disable">Ações</th></tr></thead>
        </table>
    </div></div>
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.3.2/js/dataTables.bootstrap5.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const table = new DataTable('#participantesTable', {
        processing: true,
        serverSide: true,
        ajax: @json(route('participantes.data', [], false)),
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        pagingType: 'full_numbers',
        order: [[1, 'desc']],
        columns: [
            {data: 'selecionado', orderable: false, searchable: false, className: 'text-center'},
            {data: 'id'}, {data: 'nome'}, {data: 'email'}, {data: 'cpf'}, {data: 'sexo'},
            {data: 'grupo'}, {data: 'ativo'}, {data: 'criado_em'}, {data: 'atualizado_em'},
            {data: 'acoes', orderable: false, searchable: false, className: 'text-center'}
        ],
        language: {
            processing: 'Carregando...', emptyTable: 'Nenhum participante cadastrado.',
            info: 'Exibindo _START_ a _END_ de _TOTAL_ participantes', infoEmpty: 'Nenhum participante encontrado',
            lengthMenu: 'Exibir _MENU_ registros', search: 'Pesquisar:', zeroRecords: 'Nenhum participante encontrado.',
            paginate: {first: 'Primeira', last: 'Última', next: 'Próxima', previous: 'Anterior'}
        }
    });

    const csrfToken = @json(csrf_token());
    document.getElementById('participantesTable').addEventListener('change', async event => {
        const checkbox = event.target.closest('.participante-selecao');
        if (!checkbox) return;

        const selected = checkbox.checked;
        checkbox.disabled = true;
        try {
            const response = await fetch(@json(route('participantes.selection', [], false)), {
                method: 'POST', credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken},
                body: JSON.stringify({id: Number(checkbox.value), selecionado: selected ? 1 : 0}),
            });
            if (!response.ok) throw new Error('Não foi possível atualizar a seleção.');
        } catch (error) {
            checkbox.checked = !selected;
            alert(error.message);
        } finally {
            checkbox.disabled = false;
        }
    });

    document.getElementById('clearParticipantSelection').addEventListener('click', async () => {
        if (!confirm('Tem certeza que deseja remover a seleção de todos os itens selecionados?')) return;

        try {
            const response = await fetch(@json(route('participantes.selection.clear', [], false)), {
                method: 'DELETE', credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken},
            });
            if (!response.ok) throw new Error('Não foi possível limpar a seleção.');
            table.ajax.reload(null, false);
        } catch (error) {
            alert(error.message);
        }
    });
});
</script>
@endpush
