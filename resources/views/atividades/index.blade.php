@extends('layouts.app')
@section('title', 'Atividades')
@push('styles')<link href="https://cdn.datatables.net/2.3.2/css/dataTables.bootstrap5.min.css" rel="stylesheet">@endpush

@section('content')
<div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div><h1 class="page-title">Atividades</h1><p class="page-description mb-0">Gerencie as atividades dos eventos.</p></div>
    @if(in_array('atividades.criar', $permissions, true))<a href="{{ route('atividades.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nova atividade</a>@endif
</div>
@if(session('status'))<div class="alert alert-success alert-dismissible fade show">{{ session('status') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif
<div class="card content-card"><div class="card-header"><h2 class="h5 fw-bold mb-0">Atividades cadastradas</h2></div><div class="card-body p-0"><div class="table-responsive">
    <table id="atividadesTable" class="table table-hover align-middle w-100 mb-0"><thead><tr><th>ID</th><th>Nome</th><th>Evento</th><th>Períodos</th><th>Status</th><th>Criada em</th><th>Atualizada em</th><th class="text-center" data-dt-order="disable">Ações</th></tr></thead></table>
</div></div></div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script><script src="https://cdn.datatables.net/2.3.2/js/dataTables.min.js"></script><script src="https://cdn.datatables.net/2.3.2/js/dataTables.bootstrap5.min.js"></script>
<script>document.addEventListener('DOMContentLoaded',()=>{new DataTable('#atividadesTable',{processing:true,serverSide:true,ajax:@json(route('atividades.data', [], false)),pageLength:10,lengthMenu:[10,25,50,100],pagingType:'full_numbers',order:[[0,'desc']],columns:[{data:'id'},{data:'nome'},{data:'evento'},{data:'periodos'},{data:'ativo'},{data:'criado_em'},{data:'atualizado_em'},{data:'acoes',orderable:false,searchable:false,className:'text-center'}],language:{processing:'Carregando...',emptyTable:'Nenhuma atividade cadastrada.',info:'Exibindo _START_ a _END_ de _TOTAL_ atividades',infoEmpty:'Nenhuma atividade encontrada',lengthMenu:'Exibir _MENU_ registros',search:'Pesquisar:',zeroRecords:'Nenhuma atividade encontrada.',paginate:{first:'Primeira',last:'Última',next:'Próxima',previous:'Anterior'}}})});</script>
@endpush
