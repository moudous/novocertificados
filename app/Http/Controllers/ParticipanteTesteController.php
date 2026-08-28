<?php

namespace App\Http\Controllers;

use App\Models\Participante;
use App\Models\ParticipanteTeste;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ParticipanteTesteController extends Controller
{
    private const COLUMNS = ['id', 'participante_id', 'id', 'id', 'criado_em', 'alterado_em'];

    public function index(Request $request): View
    {
        return view('participantes_teste.index', ['permissions' => (array) $request->session()->get('gi_context.permissoes', [])]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = ParticipanteTeste::withTrashed()->with('participante');
        $total = (clone $query)->count();
        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(fn (Builder $query): Builder => $query->where('id', 'like', "%{$search}%")
                ->orWhereHas('participante', fn (Builder $participant): Builder => $participant->where('nome', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")));
        }
        $filtered = (clone $query)->count();
        $column = self::COLUMNS[(int) $request->input('order.0.column', 0)] ?? 'id';
        $direction = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';
        $permissions = (array) $request->session()->get('gi_context.permissoes', []);
        $data = $query->orderBy($column, $direction)
            ->skip(max((int) $request->input('start', 0), 0))
            ->take(min(max((int) $request->input('length', 10), 1), 100))->get()
            ->map(fn (ParticipanteTeste $registro): array => [
                'id' => $registro->id,
                'participante' => e($registro->participante?->nome ?: '—'),
                'email' => e($registro->participante?->email ?: '—'),
                'estado' => $registro->trashed()
                    ? '<span class="badge text-bg-danger">Excluído</span>'
                    : '<span class="badge text-bg-success">Cadastrado</span>',
                'criado_em' => $registro->criado_em?->format('d/m/Y H:i') ?? '—',
                'alterado_em' => $registro->alterado_em?->format('d/m/Y H:i') ?? '—',
                'acoes' => view('participantes_teste.partials.actions', ['registro' => $registro, 'permissions' => $permissions])->render(),
            ]);

        return response()->json(['draw' => (int) $request->input('draw'), 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $data]);
    }

    public function participantes(Request $request): JsonResponse
    {
        $permissions = (array) $request->session()->get('gi_context.permissoes', []);
        abort_unless(in_array('participantes_teste.criar', $permissions, true) || in_array('participantes_teste.editar', $permissions, true), 403);
        $search = trim((string) $request->input('q', ''));
        $items = Participante::query()->when($search !== '', fn (Builder $query): Builder => $query->where(fn (Builder $filter): Builder => $filter->where('nome', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('nome')->paginate(20, ['id', 'nome', 'email'], 'page', max((int) $request->input('page', 1), 1));

        return response()->json(['results' => collect($items->items())->map(fn (Participante $item): array => ['id' => $item->id, 'text' => $item->nome.($item->email ? ' · '.$item->email : '')])->values(), 'pagination' => ['more' => $items->hasMorePages()]]);
    }

    public function create(): View
    {
        return view('participantes_teste.form', ['registro' => new ParticipanteTeste()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $registro = ParticipanteTeste::query()->create($this->validated($request));
        return redirect()->route('participantes_teste.show', $registro)->with('status', 'Participante de teste cadastrado com sucesso.');
    }

    public function show(ParticipanteTeste $registro): View
    {
        $registro->load('participante');
        return view('participantes_teste.show', compact('registro'));
    }

    public function edit(ParticipanteTeste $registro): View
    {
        $registro->load('participante');
        return view('participantes_teste.form', compact('registro'));
    }

    public function update(Request $request, ParticipanteTeste $registro): RedirectResponse
    {
        $registro->update($this->validated($request, $registro));
        return redirect()->route('participantes_teste.show', $registro)->with('status', 'Participante de teste atualizado com sucesso.');
    }

    public function destroy(ParticipanteTeste $registro): RedirectResponse
    {
        $registro->delete();
        return redirect()->route('participantes_teste.index')->with('status', 'Participante de teste excluído com sucesso.');
    }

    public function forceDestroy(int $registro): RedirectResponse
    {
        ParticipanteTeste::withTrashed()->findOrFail($registro)->forceDelete();
        return redirect()->route('participantes_teste.index')->with('status', 'Participante de teste excluído definitivamente.');
    }

    private function validated(Request $request, ?ParticipanteTeste $registro = null): array
    {
        return $request->validate(['participante_id' => ['required', 'integer', Rule::exists('participantes', 'id')->whereNull('excluido_em'), Rule::unique('participantes_de_teste', 'participante_id')->ignore($registro?->id)]]);
    }
}
