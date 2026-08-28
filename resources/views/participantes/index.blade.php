@extends('layouts.app')
@section('title', 'Participantes')

@push('styles')
<link href="https://cdn.datatables.net/2.3.2/css/dataTables.bootstrap5.min.css" rel="stylesheet">
@endpush

@section('content')
<div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div><h1 class="page-title">Participantes</h1><p class="page-description mb-0">Gerencie os participantes cadastrados.</p></div>
    <div class="d-flex flex-wrap gap-2">
        @if(in_array('participantes.editar', $permissions, true) && in_array('participantes.excluir_definitivamente', $permissions, true) && in_array('certificados.editar', $permissions, true))
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#mergeParticipantsModal"><i class="bi bi-people-fill me-1"></i>Unificar participantes</button>
        @endif
        @if(in_array('participantes.criar', $permissions, true))
            <a href="{{ route('participantes.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Novo participante</a>
        @endif
    </div>
</div>

@if(session('status'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('status') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card content-card">
    <div class="card-header"><h2 class="h5 fw-bold mb-0">Participantes cadastrados</h2></div>
    <div class="card-body p-0"><div class="table-responsive">
        <table id="participantesTable" class="table table-hover align-middle w-100 mb-0">
            <thead><tr><th class="text-center" data-dt-order="disable"><button id="clearParticipantSelection" type="button" class="btn btn-sm btn-link text-danger p-0" title="limpar seleção" aria-label="Limpar seleção"><i class="bi bi-trash-fill"></i></button></th><th>ID</th><th>Nome</th><th>E-mail</th><th>CPF</th><th>Qtde de Certificados</th><th>Sexo</th><th>Grupo</th><th>Status</th><th>Criado em</th><th>Atualizado em</th><th class="text-center" data-dt-order="disable">Ações</th></tr></thead>
        </table>
    </div></div>
</div>

<div class="modal fade" id="mergeParticipantsModal" tabindex="-1" aria-labelledby="mergeParticipantsTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h2 class="modal-title fs-5" id="mergeParticipantsTitle"><i class="bi bi-people-fill me-2"></i>Unificação de participantes</h2>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <strong>Como funciona:</strong> escolha um participante existente abaixo ou crie um novo participante para receber a unificação. Todos os certificados dos participantes selecionados terão o mesmo <code>participanteId</code> e o campo <code>nome</code> atualizado. Depois, os participantes de origem que ficarem sem certificados serão removidos definitivamente.
                </div>
                <div id="mergeParticipantsFeedback" class="alert d-none" role="alert"></div>
                <div id="mergeParticipantsLoading" class="text-center py-4"><span class="spinner-border text-primary" aria-hidden="true"></span><div class="mt-2">Carregando participantes selecionados...</div></div>
                <div id="mergeParticipantsContent" class="d-none">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead class="table-light"><tr><th class="text-center">#</th><th>ID</th><th>Nome</th><th>E-mail</th><th>CPF</th><th class="text-end">Certificados</th></tr></thead>
                            <tbody id="mergeParticipantsRows"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button id="mergeClearSelection" type="button" class="btn btn-outline-danger"><i class="bi bi-trash-fill me-1"></i>Limpar seleção</button>
                <div class="d-flex gap-2"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button id="mergeParticipantsSubmit" type="button" class="btn btn-primary" disabled><i class="bi bi-people-fill me-1"></i>Unificar</button></div>
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
            {data: 'id'}, {data: 'nome'}, {data: 'email'}, {data: 'cpf'}, {data: 'certificados'}, {data: 'sexo'},
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
    const modalElement = document.getElementById('mergeParticipantsModal');
    const mergeModal = modalElement ? bootstrap.Modal.getOrCreateInstance(modalElement) : null;
    let mergeCertificateTotal = 0;
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

    const clearSelection = async (confirmFirst = true) => {
        if (confirmFirst && !confirm('Tem certeza que deseja remover a seleção de todos os itens selecionados?')) return false;
        try {
            const response = await fetch(@json(route('participantes.selection.clear', [], false)), {
                method: 'DELETE', credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken},
            });
            if (!response.ok) throw new Error('Não foi possível limpar a seleção.');
            table.ajax.reload(null, false);
            return true;
        } catch (error) {
            alert(error.message);
            return false;
        }
    };

    document.getElementById('clearParticipantSelection').addEventListener('click', () => clearSelection(true));

    if (modalElement) {
        const loading = document.getElementById('mergeParticipantsLoading');
        const content = document.getElementById('mergeParticipantsContent');
        const rows = document.getElementById('mergeParticipantsRows');
        const feedback = document.getElementById('mergeParticipantsFeedback');
        const submit = document.getElementById('mergeParticipantsSubmit');
        const escapeHtml = value => $('<div>').text(value ?? '—').html();

        const showFeedback = (message, type = 'danger') => {
            feedback.textContent = message;
            feedback.className = `alert alert-${type}`;
        };

        const setNewParticipantEnabled = enabled => {
            ['mergeNewName', 'mergeNewEmail', 'mergeNewCpf'].forEach(id => {
                const input = document.getElementById(id);
                if (input) input.disabled = !enabled;
            });
            const name = document.getElementById('mergeNewName');
            if (name) name.required = enabled;
        };

        modalElement.addEventListener('show.bs.modal', async () => {
            loading.classList.remove('d-none'); content.classList.add('d-none'); feedback.classList.add('d-none'); submit.disabled = true;
            try {
                const response = await fetch(@json(route('participantes.merge.data', [], false)), {credentials: 'same-origin', headers: {'Accept': 'application/json'}});
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.message || 'Não foi possível carregar a seleção.');
                mergeCertificateTotal = payload.total_certificados;
                rows.innerHTML = payload.participantes.map((participant, index) => `<tr>
                    <td class="text-center"><input class="form-check-input merge-target" type="radio" name="merge_target" value="${participant.id}" aria-label="Unificar com ${escapeHtml(participant.nome)}"></td>
                    <td>${participant.id}</td><td>${escapeHtml(participant.nome)}</td><td>${escapeHtml(participant.email)}</td><td>${escapeHtml(participant.cpf)}</td><td class="text-end fw-semibold">${participant.certificados}</td>
                </tr>`).join('') + `<tr class="table-primary">
                    <td class="text-center"><input class="form-check-input merge-target" type="radio" name="merge_target" value="new" aria-label="Criar novo participante"></td>
                    <td>Novo</td><td><input id="mergeNewName" class="form-control" maxlength="100" placeholder="Nome do novo participante" disabled></td><td><input id="mergeNewEmail" type="email" class="form-control" maxlength="150" placeholder="E-mail" disabled></td><td><input id="mergeNewCpf" class="form-control" maxlength="11" inputmode="numeric" placeholder="CPF (11 números)" disabled></td><td class="text-end fw-bold">${mergeCertificateTotal}</td>
                </tr>`;
                if (!payload.participantes.length) showFeedback('Nenhum participante válido está selecionado.', 'warning');
                loading.classList.add('d-none'); content.classList.remove('d-none');
            } catch (error) {
                loading.classList.add('d-none'); showFeedback(error.message);
            }
        });

        rows.addEventListener('change', event => {
            if (!event.target.matches('.merge-target')) return;
            setNewParticipantEnabled(event.target.value === 'new');
            submit.disabled = false;
        });

        document.getElementById('mergeClearSelection').addEventListener('click', async () => {
            if (await clearSelection(true)) mergeModal.hide();
        });

        submit.addEventListener('click', async () => {
            const target = rows.querySelector('.merge-target:checked');
            if (!target) return showFeedback('Escolha o participante que receberá a unificação.');
            const isNew = target.value === 'new';
            const newName = document.getElementById('mergeNewName')?.value.trim() || '';
            if (isNew && !newName) return showFeedback('Informe o nome do novo participante.');
            if (!confirm('Esta operação transferirá os certificados e removerá definitivamente os participantes de origem. Deseja continuar?')) return;

            submit.disabled = true; feedback.classList.add('d-none');
            try {
                const response = await fetch(@json(route('participantes.merge', [], false)), {
                    method: 'POST', credentials: 'same-origin',
                    headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken},
                    body: JSON.stringify({
                        destino_tipo: isNew ? 'novo' : 'existente',
                        destino_id: isNew ? null : Number(target.value),
                        novo: isNew ? {nome: newName, email: document.getElementById('mergeNewEmail').value.trim() || null, cpf: document.getElementById('mergeNewCpf').value.trim() || null} : {},
                    }),
                });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.message || Object.values(payload.errors || {})[0]?.[0] || 'Não foi possível unificar os participantes.');
                showFeedback(`${payload.message} ${payload.certificados_atualizados} certificado(s) atualizado(s) e ${payload.participantes_removidos} participante(s) removido(s).`, 'success');
                table.ajax.reload(null, false);
                setTimeout(() => mergeModal.hide(), 1200);
            } catch (error) {
                showFeedback(error.message);
                submit.disabled = false;
            }
        });
    }
});
</script>
@endpush
