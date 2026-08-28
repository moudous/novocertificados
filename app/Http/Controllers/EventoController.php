<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventoController extends Controller
{
    private const COLUMNS = ['id', 'nome', 'periodos', 'ativo', 'criado_em', 'atualizado_em'];

    public function index(Request $request): View
    {
        return view('eventos.index', [
            'permissions' => (array) $request->session()->get('gi_context.permissoes', []),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = Evento::withTrashed();
        $recordsTotal = (clone $query)->count();
        $search = trim((string) $request->input('search.value', ''));

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('nome', 'like', "%{$search}%")
                    ->orWhere('periodos', 'like', "%{$search}%")
                    ->orWhere('descricao', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $query)->count();
        $columnIndex = (int) $request->input('order.0.column', 0);
        $column = self::COLUMNS[$columnIndex] ?? 'id';
        $direction = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';
        $length = min(max((int) $request->input('length', 10), 1), 100);
        $start = max((int) $request->input('start', 0), 0);
        $permissions = (array) $request->session()->get('gi_context.permissoes', []);

        $data = $query->orderBy($column, $direction)->skip($start)->take($length)->get()
            ->map(fn (Evento $evento): array => [
                'id' => $evento->id,
                'nome' => e($evento->nome ?: '—'),
                'periodos' => e($evento->periodos ?: '—'),
                'ativo' => $evento->trashed()
                    ? '<span class="badge text-bg-danger">Excluído</span>'
                    : ($evento->ativo
                        ? '<span class="badge text-bg-success">Ativo</span>'
                        : '<span class="badge text-bg-secondary">Inativo</span>'),
                'criado_em' => $evento->criado_em?->format('d/m/Y H:i') ?? '—',
                'atualizado_em' => $evento->atualizado_em?->format('d/m/Y H:i') ?? '—',
                'acoes' => view('eventos.partials.actions', [
                    'evento' => $evento,
                    'permissions' => $permissions,
                ])->render(),
            ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function create(): View
    {
        return view('eventos.form', ['evento' => new Evento()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $evento = Evento::query()->create($this->validated($request));

        return redirect()->route('eventos.show', $evento)->with('status', 'Evento cadastrado com sucesso.');
    }

    public function show(Evento $evento): View
    {
        return view('eventos.show', compact('evento'));
    }

    public function edit(Evento $evento): View
    {
        return view('eventos.form', compact('evento'));
    }

    public function update(Request $request, Evento $evento): RedirectResponse
    {
        $evento->update($this->validated($request));

        return redirect()->route('eventos.show', $evento)->with('status', 'Evento atualizado com sucesso.');
    }

    public function destroy(Evento $evento): RedirectResponse
    {
        $evento->delete();

        return redirect()->route('eventos.index')->with('status', 'Evento excluído com sucesso.');
    }

    public function toggleStatus(Evento $evento): RedirectResponse
    {
        $evento->update(['ativo' => ! $evento->ativo]);

        return redirect()->route('eventos.index')->with('status', 'Status do evento atualizado com sucesso.');
    }

    public function restore(int $evento): RedirectResponse
    {
        Evento::withTrashed()->findOrFail($evento)->restore();

        return redirect()->route('eventos.index')->with('status', 'Evento restaurado com sucesso.');
    }

    public function forceDestroy(int $evento): RedirectResponse
    {
        Evento::withTrashed()->findOrFail($evento)->forceDelete();

        return redirect()->route('eventos.index')->with('status', 'Evento excluído definitivamente.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nome' => ['nullable', 'string', 'max:200'],
            'periodos' => ['nullable', 'string', 'max:100'],
            'ativo' => ['required', 'boolean'],
            'descricao' => ['nullable', 'string'],
        ]);
    }
}
