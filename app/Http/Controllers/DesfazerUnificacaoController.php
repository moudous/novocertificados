<?php

namespace App\Http\Controllers;

use App\Models\UnificacaoRealizada;
use App\Services\UnificationHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DesfazerUnificacaoController extends Controller
{
    private const COLUMNS = ['id', 'participante_novo_nome', null, 'usuario_nome', 'criado_em', 'status', null];

    public function index(Request $request): View
    {
        return view('desfazer_unificacao.index', ['permissions' => (array) $request->session()->get('gi_context.permissoes', [])]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = UnificacaoRealizada::query();
        $total = (clone $query)->count();
        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(fn ($builder) => $builder->where('id', 'like', "%{$search}%")
                ->orWhere('participante_novo_nome', 'like', "%{$search}%")
                ->orWhere('usuario_nome', 'like', "%{$search}%")
                ->orWhere('status', 'like', "%{$search}%"));
        }
        $filtered = (clone $query)->count();
        $column = self::COLUMNS[(int) $request->input('order.0.column', 0)] ?? 'id';
        $direction = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';
        $permissions = (array) $request->session()->get('gi_context.permissoes', []);
        $canView = in_array('desfazerunificacao.visualizar', $permissions, true);

        $data = $query->orderBy($column, $direction)
            ->skip(max((int) $request->input('start', 0), 0))
            ->take(min(max((int) $request->input('length', 10), 1), 100))
            ->get()->map(function (UnificacaoRealizada $item) use ($canView): array {
                $url = route('desfazerunificacao.show', $item);
                $name = e($item->participante_novo_nome ?: 'Participante #'.$item->participante_novo_id);
                $excluded = collect($item->participantes_excluidos)->map(fn (array $participant): string => '#'.(int) $participant['id'].' · '.e($participant['nome'] ?? '—'))->implode('<br>');
                return [
                    'id' => $item->id,
                    'participante_novo' => $canView ? '<a href="'.e($url).'">'.$name.'</a><div class="small text-muted">ID '.$item->participante_novo_id.'</div>' : $name,
                    'participantes_excluidos' => $excluded ?: '—',
                    'usuario' => e($item->usuario_nome ?: ($item->usuario_id ? 'Usuário #'.$item->usuario_id : '—')),
                    'data' => $item->criado_em?->format('d/m/Y H:i:s') ?? '—',
                    'status' => $item->status === 'desfeita' ? '<span class="badge text-bg-secondary">Desfeita</span>' : '<span class="badge text-bg-success">Realizada</span>',
                    'acoes' => $canView ? '<a href="'.e($url).'" class="btn btn-sm btn-outline-primary" title="Visualizar unificação" aria-label="Visualizar unificação"><i class="bi bi-eye-fill"></i></a>' : '—',
                ];
            });

        return response()->json(['draw' => (int) $request->input('draw'), 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $data]);
    }

    public function show(Request $request, UnificacaoRealizada $unificacao): View
    {
        return view('desfazer_unificacao.show', [
            'unificacao' => $unificacao,
            'permissions' => (array) $request->session()->get('gi_context.permissoes', []),
        ]);
    }

    public function undo(Request $request, UnificacaoRealizada $unificacao, UnificationHistoryService $history): RedirectResponse
    {
        $result = $history->undo($unificacao, $request);
        if ($result['conflicts'] !== []) {
            return back()->withErrors(['unificacao' => 'Não foi possível desfazer automaticamente porque existem conflitos. Faça a intervenção manual indicada e volte a esta página.'])
                ->with('undo_conflicts', $result['conflicts']);
        }

        return back()->with('status', 'Unificação desfeita com sucesso. '.$result['restored_participants'].' participante(s) restaurado(s) com seus IDs originais.');
    }
}
