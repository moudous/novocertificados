<?php

namespace App\Http\Controllers;

use App\Models\Participante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ParticipanteController extends Controller
{
    private const COLUMNS = ['id', 'nome', 'email', 'cpf', 'sexo', 'grupo', 'ativo', 'criado_em', 'atualizado_em'];

    public function index(Request $request): View
    {
        return view('participantes.index', [
            'permissions' => (array) $request->session()->get('gi_context.permissoes', []),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = Participante::query()->withTrashed();
        $recordsTotal = (clone $query)->count();
        $search = trim((string) $request->input('search.value', ''));

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('nome', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('cpf', 'like', "%{$search}%")
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
            ->map(fn (Participante $participante): array => [
                'id' => $participante->id,
                'nome' => e($participante->nome),
                'email' => e($participante->email ?: '—'),
                'cpf' => e($participante->cpf ?: '—'),
                'sexo' => e($participante->sexo ?: '—'),
                'grupo' => e($participante->grupo ?: '—'),
                'ativo' => $participante->ativo
                    ? '<span class="badge text-bg-success">Ativo</span>'
                    : '<span class="badge text-bg-secondary">Inativo</span>',
                'criado_em' => $participante->criado_em?->format('d/m/Y H:i') ?? '—',
                'atualizado_em' => $participante->atualizado_em?->format('d/m/Y H:i') ?? '—',
                'acoes' => view('participantes.partials.actions', [
                    'participante' => $participante,
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
        return view('participantes.form', ['participante' => new Participante()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $participante = Participante::query()->create($this->validated($request));

        return redirect()->route('participantes.show', $this->routeParameters($participante))
            ->with('status', 'Participante cadastrado com sucesso.');
    }

    public function show(int $id, string $nome): View
    {
        return view('participantes.show', ['participante' => $this->find($id, $nome)]);
    }

    public function edit(int $id, string $nome): View
    {
        return view('participantes.form', ['participante' => $this->find($id, $nome)]);
    }

    public function update(Request $request, int $id, string $nome): RedirectResponse
    {
        $participante = $this->find($id, $nome);
        $participante->update($this->validated($request));

        return redirect()->route('participantes.show', $this->routeParameters($participante))
            ->with('status', 'Participante atualizado com sucesso.');
    }

    public function destroy(int $id, string $nome): RedirectResponse
    {
        $this->find($id, $nome)->delete();

        return redirect()->route('participantes.index')->with('status', 'Participante excluído com sucesso.');
    }

    public function restore(int $id, string $nome): RedirectResponse
    {
        $this->find($id, $nome, true)->restore();

        return redirect()->route('participantes.index')->with('status', 'Participante restaurado com sucesso.');
    }

    public function forceDestroy(int $id, string $nome): RedirectResponse
    {
        $this->find($id, $nome, true)->forceDelete();

        return redirect()->route('participantes.index')->with('status', 'Participante excluído definitivamente.');
    }

    private function find(int $id, string $nome, bool $withTrashed = false): Participante
    {
        $query = $withTrashed ? Participante::withTrashed() : Participante::query();

        return $query->whereIdentity($id, $nome)->firstOrFail();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'sexo' => ['nullable', 'string', 'in:M,F'],
            'grupo' => ['nullable', 'string', 'max:1'],
            'ativo' => ['required', 'boolean'],
            'cpf' => ['nullable', 'digits:11'],
        ]);
    }

    private function routeParameters(Participante $participante): array
    {
        return ['id' => $participante->id, 'nome' => $participante->nome];
    }
}
