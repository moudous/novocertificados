@extends('layouts.app')
@section('title', 'Certificados novos')
@push('styles')
<link href="https://cdn.datatables.net/2.3.2/css/dataTables.bootstrap5.min.css" rel="stylesheet">
@endpush
@section('content')
<div class="mb-4"><h1 class="page-title">Certificados novos</h1><p class="page-description mb-0">Consulte e gerencie os certificados dos participantes.</p></div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="card content-card"><div class="card-body p-0"><div class="table-responsive"><table id="certificadosTable" class="table table-hover align-middle w-100 mb-0"><thead><tr><th>ID</th><th>Emissão</th><th>Template</th><th>Participante</th><th>Evento</th><th>Atividade</th><th>Data do certificado</th><th>Horas</th><th>PDF</th><th>Status</th><th data-dt-order="disable">Ações</th></tr></thead></table></div></div></div>
@endsection
@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script><script src="https://cdn.datatables.net/2.3.2/js/dataTables.min.js"></script><script src="https://cdn.datatables.net/2.3.2/js/dataTables.bootstrap5.min.js"></script>
<script>new DataTable('#certificadosTable',{processing:true,serverSide:true,ajax:@json(route('certificadosnovos.data',[],false)),order:[[0,'desc']],columns:[{data:'id'},{data:'emissao'},{data:'template'},{data:'participante'},{data:'evento'},{data:'atividade'},{data:'data_certificado'},{data:'horas'},{data:'pdf',orderable:false,searchable:false},{data:'ativo'},{data:'acoes',orderable:false,searchable:false}],language:{processing:'Carregando...',emptyTable:'Nenhum certificado encontrado.',info:'Exibindo _START_ a _END_ de _TOTAL_ certificados',infoEmpty:'Nenhum certificado encontrado',search:'Pesquisar:',zeroRecords:'Nenhum certificado encontrado.'}})</script>
@endpush
