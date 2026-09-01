@extends('layouts.app')
@section('title', 'Desfazer unificações')
@push('styles')
<link href="https://cdn.datatables.net/2.3.2/css/dataTables.bootstrap5.min.css" rel="stylesheet">
@endpush
@section('content')
<div class="mb-4"><h1 class="page-title">Desfazer unificações</h1><p class="page-description mb-0">Consulte o histórico das unificações de participantes e restaure os registros quando necessário.</p></div>
<div class="card content-card"><div class="card-body p-0"><div class="table-responsive">
<table id="unificationsTable" class="table table-hover align-middle w-100 mb-0"><thead><tr><th>ID</th><th>Participante novo</th><th>Participantes excluídos</th><th>Realizada por</th><th>Data e hora</th><th>Status</th><th class="text-center">Ação</th></tr></thead></table>
</div></div></div>
@endsection
@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.3.2/js/dataTables.bootstrap5.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded',()=>new DataTable('#unificationsTable',{processing:true,serverSide:true,ajax:@json(route('desfazerunificacao.data',[],false)),order:[[0,'desc']],columns:[{data:'id'},{data:'participante_novo'},{data:'participantes_excluidos',orderable:false,searchable:false},{data:'usuario'},{data:'data'},{data:'status'},{data:'acoes',orderable:false,searchable:false,className:'text-center'}],language:{processing:'Carregando...',emptyTable:'Nenhuma unificação registrada.',info:'Exibindo _START_ a _END_ de _TOTAL_ unificações',infoEmpty:'Nenhuma unificação encontrada',search:'Pesquisar:',zeroRecords:'Nenhuma unificação encontrada.',paginate:{first:'Primeira',last:'Última',next:'Próxima',previous:'Anterior'}}}));
</script>
@endpush
