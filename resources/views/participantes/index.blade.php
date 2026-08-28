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
            <thead><tr><th>ID</th><th>Nome</th><th>E-mail</th><th>CPF</th><th>Sexo</th><th>Grupo</th><th>Status</th><th>Criado em</th><th>Atualizado em</th><th class="text-center" data-dt-order="disable">Ações</th></tr></thead>
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
    new DataTable('#participantesTable', {
        processing: true,
        serverSide: true,
        ajax: @json(route('participantes.data', [], false)),
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        pagingType: 'full_numbers',
        order: [[0, 'desc']],
        columns: [
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
});
</script>
@endpush
