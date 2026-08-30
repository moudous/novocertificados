<?php

namespace App\Http\Controllers;

use App\Models\Participante;
use App\Models\Responsavel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ResponsavelController extends Controller
{
    private const COLUMNS = ['id', 'participante_id', 'cargo', 'titulacao', 'ativo', 'criado_em', 'alterado_em'];

    public function index(Request $request): View
    {
        return view('responsaveis.index', ['permissions' => (array) $request->session()->get('gi_context.permissoes', [])]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = Responsavel::withTrashed()->with('participante');
        $total = (clone $query)->count();
        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(fn (Builder $query): Builder => $query->where('id', 'like', "%{$search}%")
                ->orWhere('cargo', 'like', "%{$search}%")
                ->orWhere('titulacao', 'like', "%{$search}%")
                ->orWhereHas('participante', fn (Builder $participant): Builder => $participant->where('nome', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")));
        }
        $filtered = (clone $query)->count();
        $column = self::COLUMNS[(int) $request->input('order.0.column', 0)] ?? 'id';
        $direction = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';
        $permissions = (array) $request->session()->get('gi_context.permissoes', []);
        $data = $query->orderBy($column, $direction)->skip(max((int) $request->input('start', 0), 0))
            ->take(min(max((int) $request->input('length', 10), 1), 100))->get()
            ->map(fn (Responsavel $responsavel): array => [
                'id' => $responsavel->id,
                'participante' => e($responsavel->participante?->nome ?: '—'),
                'cargo' => e($responsavel->cargo ?: '—'),
                'titulacao' => e($responsavel->titulacao ?: '—'),
                'ativo' => $responsavel->trashed() ? '<span class="badge text-bg-danger">Excluído</span>' : ($responsavel->ativo ? '<span class="badge text-bg-success">Ativo</span>' : '<span class="badge text-bg-secondary">Inativo</span>'),
                'criado_em' => $responsavel->criado_em?->format('d/m/Y H:i') ?? '—',
                'alterado_em' => $responsavel->alterado_em?->format('d/m/Y H:i') ?? '—',
                'acoes' => view('responsaveis.partials.actions', compact('responsavel', 'permissions'))->render(),
            ]);

        return response()->json(['draw' => (int) $request->input('draw'), 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $data]);
    }

    public function participantes(Request $request): JsonResponse
    {
        $permissions = (array) $request->session()->get('gi_context.permissoes', []);
        abort_unless(in_array('responsaveis.criar', $permissions, true) || in_array('responsaveis.editar', $permissions, true), 403);
        $search = trim((string) $request->input('q', ''));
        $responsavelId = $request->integer('responsavel_id') ?: null;
        $items = Participante::query()
            ->whereDoesntHave('responsavel', fn (Builder $query): Builder => $query->when($responsavelId, fn (Builder $filter): Builder => $filter->whereKeyNot($responsavelId)))
            ->when($search !== '', fn (Builder $query): Builder => $query->where(fn (Builder $filter): Builder => $filter->where('nome', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('nome')->paginate(20, ['id', 'nome', 'email'], 'page', max((int) $request->input('page', 1), 1));

        return response()->json(['results' => collect($items->items())->map(fn (Participante $item): array => ['id' => $item->id, 'text' => $item->nome.($item->email ? ' · '.$item->email : '')])->values(), 'pagination' => ['more' => $items->hasMorePages()]]);
    }

    public function create(): View
    {
        return view('responsaveis.form', ['responsavel' => new Responsavel()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $responsavel = Responsavel::query()->create($this->validated($request));
        return redirect()->route('responsaveis.show', $responsavel)->with('status', 'Responsável cadastrado com sucesso.');
    }

    public function show(Responsavel $responsavel): View
    {
        $responsavel->load('participante');
        return view('responsaveis.show', compact('responsavel'));
    }

    public function edit(Responsavel $responsavel): View
    {
        $responsavel->load('participante');
        return view('responsaveis.form', compact('responsavel'));
    }

    public function update(Request $request, Responsavel $responsavel): RedirectResponse
    {
        $responsavel->update($this->validated($request, $responsavel));
        return redirect()->route('responsaveis.show', $responsavel)->with('status', 'Responsável atualizado com sucesso.');
    }

    public function toggleStatus(Responsavel $responsavel): RedirectResponse
    {
        $responsavel->update(['ativo' => ! $responsavel->ativo]);
        return redirect()->route('responsaveis.index')->with('status', 'Status atualizado com sucesso.');
    }

    public function destroy(Responsavel $responsavel): RedirectResponse
    {
        $responsavel->delete();
        return redirect()->route('responsaveis.index')->with('status', 'Responsável excluído com sucesso.');
    }

    public function restore(int $responsavel): RedirectResponse
    {
        Responsavel::onlyTrashed()->findOrFail($responsavel)->restore();
        return redirect()->route('responsaveis.index')->with('status', 'Responsável restaurado com sucesso.');
    }

    public function forceDestroy(int $responsavel): RedirectResponse
    {
        Responsavel::withTrashed()->findOrFail($responsavel)->forceDelete();
        return redirect()->route('responsaveis.index')->with('status', 'Responsável excluído definitivamente.');
    }

    private function validated(Request $request, ?Responsavel $responsavel = null): array
    {
        return $request->validate([
            'participante_id' => ['required', 'integer', Rule::exists('participantes', 'id')->whereNull('excluido_em'), Rule::unique('responsaveis', 'participante_id')->ignore($responsavel?->id)],
            'cargo' => ['nullable', 'string', 'max:100'],
            'titulacao' => ['nullable', 'string', 'max:100'],
            'ativo' => ['required', 'boolean'],
        ]);
    }
}
