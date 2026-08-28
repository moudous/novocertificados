@extends('layouts.app')
@section('title','Certificados')
@push('styles')
<link href="https://cdn.datatables.net/2.3.2/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
<style>
#certificadosTable thead .filter-row th { padding: .4rem; vertical-align: top; }
#certificadosTable .column-filter { min-width: 90px; font-size: .8rem; }
#certificadosTable .select2-container { min-width: 160px; font-size: .8rem; }
#certificadosTable .select2-selection--single { min-height: 31px; font-size: .8rem; }
</style>
@endpush
@section('content')
<div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-3"><div><h1 class="page-title">Certificados</h1><p class="page-description mb-0">Gerencie os certificados cadastrados.</p></div>@if(in_array('certificados.criar',$permissions,true))<a href="{{ route('certificados.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Novo certificado</a>@endif</div>
@if(session('status'))<div class="alert alert-success alert-dismissible fade show">{{ session('status') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif
<div class="card content-card"><div class="card-header"><h2 class="h5 fw-bold mb-0">Certificados cadastrados</h2></div><div class="card-body p-0"><div class="table-responsive"><table id="certificadosTable" class="table table-hover align-middle w-100 mb-0"><thead>
<tr><th>ID</th><th>Nome</th><th>Participante</th><th>Atividade</th><th>Tipo</th><th>Carga horária</th><th>Status</th><th>Criado em</th><th>Atualizado em</th><th class="text-center" data-dt-order="disable">Ações</th></tr>
<tr class="filter-row">
<th><input type="text" inputmode="numeric" pattern="[0-9]*" class="form-control form-control-sm column-filter numeric-filter" data-filter="id" aria-label="Filtrar por ID"></th>
<th><input type="text" class="form-control form-control-sm column-filter" data-filter="nome" aria-label="Filtrar por nome"></th>
<th><select id="participanteFilter" class="column-filter" data-filter="participanteId" aria-label="Filtrar por participante"><option value=""></option></select></th>
<th><select id="atividadeFilter" class="column-filter" data-filter="atividadeId" aria-label="Filtrar por atividade"><option value=""></option></select></th>
<th><input type="text" class="form-control form-control-sm column-filter" data-filter="tipo" aria-label="Filtrar por tipo"></th>
<th><input type="text" inputmode="numeric" pattern="[0-9]*" class="form-control form-control-sm column-filter numeric-filter" data-filter="cargaHoraria" aria-label="Filtrar por carga horária"></th>
<th><select class="form-select form-select-sm column-filter" data-filter="status" aria-label="Filtrar por status"><option value="">Todos</option><option value="ativo">Ativo</option><option value="inativo">Inativo</option><option value="excluido">Excluído</option></select></th>
<th><input type="date" class="form-control form-control-sm column-filter" data-filter="criado_em" aria-label="Filtrar por data de criação"></th>
<th></th><th></th>
</tr>
</thead></table></div></div></div>
@endsection
@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script><script src="https://cdn.datatables.net/2.3.2/js/dataTables.min.js"></script><script src="https://cdn.datatables.net/2.3.2/js/dataTables.bootstrap5.min.js"></script><script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const filters = {};
    const table = new DataTable('#certificadosTable', {
        processing: true, serverSide: true,
        ajax: {
            url: @json(route('certificados.data', [], false)),
            data: request => { request.filters = filters; }
        },
        pageLength: 10, lengthMenu: [10, 25, 50, 100], pagingType: 'full_numbers', order: [[0, 'desc']], orderCellsTop: true,
        columns: [{data:'id'},{data:'nome'},{data:'participante'},{data:'atividade'},{data:'tipo'},{data:'cargaHoraria'},{data:'ativo'},{data:'criado_em'},{data:'atualizado_em'},{data:'acoes',orderable:false,searchable:false,className:'text-center'}],
        language: {processing:'Carregando...',emptyTable:'Nenhum certificado cadastrado.',info:'Exibindo _START_ a _END_ de _TOTAL_ certificados',infoEmpty:'Nenhum certificado encontrado',lengthMenu:'Exibir _MENU_ registros',search:'Pesquisar:',zeroRecords:'Nenhum certificado encontrado.',paginate:{first:'Primeira',last:'Última',next:'Próxima',previous:'Anterior'}}
    });
    let filterTimer;
    document.querySelectorAll('#certificadosTable .column-filter:not(select)').forEach(filter => {
        filter.addEventListener('input', () => {
            if (filter.classList.contains('numeric-filter')) filter.value = filter.value.replace(/\D/g, '');
            clearTimeout(filterTimer);
            filters[filter.dataset.filter] = filter.value;
            filterTimer = setTimeout(() => table.draw(), 350);
        });
        filter.addEventListener('click', event => event.stopPropagation());
    });
    document.querySelectorAll('#certificadosTable select.column-filter').forEach(filter => {
        filter.addEventListener('change', () => {
            filters[filter.dataset.filter] = filter.value;
            table.draw();
        });
        filter.addEventListener('click', event => event.stopPropagation());
    });
    const setupSelect2 = (selector, url, placeholder) => $(selector).select2({
        theme: 'bootstrap-5', width: '100%', placeholder, allowClear: true,
        ajax: {url, dataType: 'json', delay: 250, data: params => ({q: params.term || '', page: params.page || 1}), processResults: response => response}
    });
    setupSelect2('#participanteFilter', @json(route('certificados.participantes', [], false)), 'Todos');
    setupSelect2('#atividadeFilter', @json(route('certificados.atividades', [], false)), 'Todas');
});
</script>
@endpush
