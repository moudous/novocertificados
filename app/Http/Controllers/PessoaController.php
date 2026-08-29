<?php

namespace App\Http\Controllers;

use App\Models\Pessoa;
use App\Services\GiPessoaSynchronizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class PessoaController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeGiSession($request);

        return view('pessoas.index');
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorizeGiSession($request);

        $columns = ['id', 'nome', 'email', 'perfil', 'ativo', 'ultimo_acesso', 'updated_at'];
        $query = Pessoa::query();
        $recordsTotal = (clone $query)->count();
        $search = trim((string) $request->input('search.value', ''));

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('nome', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('perfil', 'like', "%{$search}%")
                    ->orWhere('usuario', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $query)->count();
        $columnIndex = (int) $request->input('order.0.column', 0);
        $column = $columns[$columnIndex] ?? 'id';
        $direction = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';
        $length = min(max((int) $request->input('length', 10), 1), 100);
        $start = max((int) $request->input('start', 0), 0);

        $data = $query->orderBy($column, $direction)->skip($start)->take($length)->get()
            ->map(fn (Pessoa $pessoa): array => [
                'id' => $pessoa->id,
                'nome' => e($pessoa->nome),
                'email' => e($pessoa->email),
                'perfil' => collect($pessoa->perfis ?: [[
                    'id' => $pessoa->perfil_id,
                    'nome' => $pessoa->perfil,
                ]])->filter(fn ($perfil): bool => filled($perfil['nome'] ?? null))
                    ->map(function (array $perfil) use ($pessoa): string {
                        $ultimo = (int) ($perfil['id'] ?? 0) === (int) $pessoa->perfil_id
                            && $pessoa->ultimo_acesso !== null;
                        $cor = $ultimo ? 'text-bg-primary' : 'text-bg-secondary';
                        $titulo = $ultimo ? ' title="Perfil do último acesso"' : '';

                        return '<span class="badge '.$cor.'"'.$titulo.'>'.e($perfil['nome']).'</span>';
                    })->implode(', '),
                'ativo' => $pessoa->ativo
                    ? '<span class="badge text-bg-success">Ativa</span>'
                    : '<span class="badge text-bg-secondary">Inativa</span>',
                'ultimo_acesso' => $pessoa->ultimo_acesso?->format('d/m/Y H:i') ?? 'Nunca acessou',
                'updated_at' => $pessoa->ultimaSincronizacaoLocal()?->format('d/m/Y H:i') ?? '—',
                'acoes' => '<a href="'.e(route('pessoas.show', $pessoa)).'" class="btn btn-sm btn-outline-dark listagem-acao" title="Visualizar pessoa" aria-label="Visualizar '.e($pessoa->nome).'"><i class="bi bi-eye-fill"></i></a>',
            ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function show(Request $request, Pessoa $pessoa): View
    {
        $this->authorizeGiSession($request);

        return view('pessoas.show', compact('pessoa'));
    }

    public function import(Request $request, GiPessoaSynchronizer $synchronizer): JsonResponse
    {
        $this->authorizeGiSession($request);

        $accessToken = (string) $request->session()->get('gi_context.access_token', '');
        abort_if($accessToken === '', 401, 'Token de acesso do GI não encontrado. Abra novamente pelo menu do GI.');

        $total = $synchronizer->syncFromGi($accessToken);

        return response()->json([
            'message' => "$total pessoa(s) importada(s) com sucesso.",
            'total' => $total,
        ]);
    }

    private function authorizeGiSession(Request $request): void
    {
        abort_unless($request->session()->has('gi_context'), 401, 'Abra esta aplicação pelo menu do GI.');
    }
}
