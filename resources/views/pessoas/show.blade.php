@extends('layouts.app')
@section('title', 'Visualizar pessoa')

@section('content')
<div class="page-container">
    <div class="mb-4"><h1 class="page-title">Visualizar pessoa</h1><p class="page-description mb-0">Dados sincronizados com o sistema GI.</p></div>
    <div class="card content-card">
        <div class="card-header"><h2 class="h5 fw-bold mb-0">Dados da pessoa</h2></div>
        <div class="card-body p-4">
            @php
                $fields = [
                    ['ID do usuário no GI', $pessoa->id], ['Nome', $pessoa->nome],
                    ['Usuário', $pessoa->usuario], ['E-mail', $pessoa->email],
                    ['Perfil', $pessoa->perfil], ['ID do perfil', $pessoa->perfil_id],
                    ['Status', $pessoa->ativo ? 'Ativa' : 'Inativa'],
                    ['Data de cadastro', $pessoa->created_at?->format('d/m/Y H:i')],
                    ['Última sincronização', $pessoa->updated_at?->format('d/m/Y H:i')],
                ];
            @endphp
            <div class="row g-3">
                @foreach($fields as [$label, $value])
                    <div class="col-12 col-md-6"><div class="form-label fw-semibold">{{ $label }}</div><div class="form-control bg-body-tertiary h-auto text-break">{{ filled($value) ? $value : '—' }}</div></div>
                @endforeach
            </div>
            <div class="d-flex justify-content-end mt-4"><a href="{{ route('pessoas.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Voltar</a></div>
        </div>
    </div>
</div>
@endsection
