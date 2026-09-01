<?php

namespace App\Http\Controllers;

use App\Models\Participante;
use App\Services\UnificationHistoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ParticipanteController extends Controller
{
    private const COLUMNS = [null, 'id', 'nome', 'email', 'cpf', 'certificados_count', 'sexo', 'grupo', 'ativo', 'criado_em', 'atualizado_em'];

    public function index(Request $request): View
    {
        return view('participantes.index', [
            'permissions' => (array) $request->session()->get('gi_context.permissoes', []),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = Participante::query()->withTrashed()->withCount('certificados');
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
        $selectedParticipants = array_map('intval', (array) $request->session()->get('selecao_participantes', []));

        $data = $query->orderBy($column, $direction)->skip($start)->take($length)->get()
            ->map(fn (Participante $participante): array => [
                'selecionado' => sprintf(
                    '<input type="checkbox" class="form-check-input participante-selecao" value="%d" aria-label="Selecionar %s"%s>',
                    $participante->id,
                    e($participante->nome),
                    in_array($participante->id, $selectedParticipants, true) ? ' checked' : '',
                ),
                'id' => $participante->id,
                'nome' => e($participante->nome),
                'email' => e($participante->email ?: '—'),
                'cpf' => e($participante->cpf ?: '—'),
                'certificados' => $participante->certificados_count > 0
                    ? sprintf(
                        '<a href="%s/?participante=%s#certificados" target="_blank" rel="noopener noreferrer" title="ver certificados">%d</a>',
                        e(rtrim((string) config('app.site_certificados'), '/')),
                        rawurlencode($participante->nome),
                        $participante->certificados_count,
                    )
                    : $participante->certificados_count,
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

    public function selection(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer', 'exists:participantes,id'],
            'selecionado' => ['required', 'boolean'],
        ]);

        $selected = collect((array) $request->session()->get('selecao_participantes', []))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ((bool) $data['selecionado']) {
            $selected->push((int) $data['id']);
        } else {
            $selected = $selected->reject(fn (int $id): bool => $id === (int) $data['id']);
        }

        $selected = $selected->unique()->values();
        $request->session()->put('selecao_participantes', $selected->all());

        return response()->json([
            'id' => (int) $data['id'],
            'selecionado' => (bool) $data['selecionado'],
            'total' => $selected->count(),
        ]);
    }

    public function clearSelection(Request $request): JsonResponse
    {
        $request->session()->forget('selecao_participantes');

        return response()->json(['message' => 'Seleção de participantes limpa.', 'total' => 0]);
    }

    public function mergeData(Request $request): JsonResponse
    {
        $selectedIds = $this->selectedIds($request);
        $participants = Participante::query()
            ->whereIn('id', $selectedIds)
            ->orderBy('nome')
            ->get(['id', 'nome', 'email', 'cpf']);

        $validIds = $participants->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $request->session()->put('selecao_participantes', $validIds);

        $legacyCertificateCounts = DB::table('certificados')
            ->whereIn('participanteId', $validIds)
            ->selectRaw('participanteId, count(*) as total')
            ->groupBy('participanteId')
            ->pluck('total', 'participanteId');
        $newCertificateCounts = DB::table('lista_participantes')
            ->whereIn('participante_id', $validIds)
            ->selectRaw('participante_id, count(*) as total')
            ->groupBy('participante_id')
            ->pluck('total', 'participante_id');

        return response()->json([
            'participantes' => $participants->map(fn (Participante $participant): array => [
                'id' => $participant->id,
                'nome' => $participant->nome,
                'email' => $participant->email,
                'cpf' => $participant->cpf,
                'certificados_legados' => (int) ($legacyCertificateCounts[$participant->id] ?? 0),
                'certificados_novos' => (int) ($newCertificateCounts[$participant->id] ?? 0),
            ]),
            'total_certificados_legados' => (int) $legacyCertificateCounts->sum(),
            'total_certificados_novos' => (int) $newCertificateCounts->sum(),
        ]);
    }

    public function merge(Request $request, UnificationHistoryService $history): JsonResponse
    {
        $data = $request->validate([
            'destino_tipo' => ['required', 'in:existente,novo'],
            'destino_id' => ['nullable', 'integer'],
            'novo.nome' => ['nullable', 'required_if:destino_tipo,novo', 'string', 'max:100'],
            'novo.email' => ['nullable', 'email', 'max:150'],
            'novo.cpf' => ['nullable', 'digits:11'],
        ]);
        $selectedIds = $this->selectedIds($request);

        if ($selectedIds === []) {
            throw ValidationException::withMessages(['selecao' => 'Selecione ao menos um participante.']);
        }

        $result = DB::transaction(function () use ($data, $selectedIds, $history, $request): array {
            $participants = DB::table('participantes')
                ->whereIn('id', $selectedIds)
                ->whereNull('excluido_em')
                ->lockForUpdate()
                ->get(['id', 'nome']);

            if ($participants->count() !== count($selectedIds)) {
                throw ValidationException::withMessages(['selecao' => 'A seleção mudou. Reabra a unificação e tente novamente.']);
            }

            $before = $history->capture($selectedIds);
            $targetCreated = $data['destino_tipo'] === 'novo';

            if ($data['destino_tipo'] === 'existente') {
                $targetId = (int) ($data['destino_id'] ?? 0);
                $target = $participants->firstWhere('id', $targetId);
                if (! $target) {
                    throw ValidationException::withMessages(['destino_id' => 'Escolha um participante da seleção.']);
                }
                $targetName = (string) $target->nome;
            } else {
                $targetName = trim((string) data_get($data, 'novo.nome'));
                $now = now();
                $targetId = (int) DB::table('participantes')->insertGetId([
                    'nome' => $targetName,
                    'email' => data_get($data, 'novo.email'),
                    'cpf' => data_get($data, 'novo.cpf'),
                    'ativo' => 1,
                    'email_ficticio' => 0,
                    'criado_por' => $this->sessionUserId($request),
                    'criado_em' => $now,
                    'atualizado_em' => $now,
                ]);
            }

            $sourceIds = collect($selectedIds)->reject(fn (int $id): bool => $id === $targetId)->values()->all();
            $legacyCertificatesUpdated = DB::table('certificados')->whereIn('participanteId', $sourceIds)->count();
            DB::table('certificados')
                ->whereIn('participanteId', $selectedIds)
                ->update([
                    'participanteId' => $targetId,
                    'nome' => $targetName,
                    'atualizado_em' => now(),
                ]);

            $newRows = DB::table('lista_participantes')
                ->whereIn('participante_id', $selectedIds)
                ->lockForUpdate()
                ->get();
            $newCertificatesUpdated = $newRows->whereIn('participante_id', $sourceIds)->count();

            foreach ($newRows->groupBy('novo_certificado_id') as $emissionId => $rows) {
                $generated = $rows->filter(fn (object $row): bool => collect([
                    $row->codigo ?? null,
                    $row->codigo_img ?? null,
                    $row->arquivo_pdf ?? null,
                    $row->arquivo_img ?? null,
                ])->contains(fn ($value): bool => filled($value)));

                if ($generated->count() > 1) {
                    throw ValidationException::withMessages([
                        'selecao' => "A emissão #{$emissionId} possui mais de um certificado PDF/IMG gerado para os participantes selecionados. A unificação foi cancelada para evitar perda de arquivos.",
                    ]);
                }

                $keeper = $generated->first()
                    ?? $rows->firstWhere('participante_id', $targetId)
                    ?? $rows->first();
                $discardIds = $rows->pluck('id')->reject(fn ($id): bool => (int) $id === (int) $keeper->id)->values();

                if ($discardIds->isNotEmpty()) {
                    DB::table('novos_certificados')
                        ->whereIn('lista_participantes_id', $discardIds)
                        ->update(['lista_participantes_id' => $keeper->id]);
                    DB::table('lista_participantes')->whereIn('id', $discardIds)->delete();
                }

                if ((int) $keeper->participante_id !== $targetId) {
                    DB::table('lista_participantes')->where('id', $keeper->id)->update([
                        'participante_id' => $targetId,
                        'alterado_em' => now(),
                    ]);
                }
            }

            DB::table('rubricas_participantes')->whereIn('participante_id', $sourceIds)->update([
                'participante_id' => $targetId,
                'alterado_em' => now(),
            ]);
            DB::table('assinaturas_template')->whereIn('participante_id', $sourceIds)->update([
                'participante_id' => $targetId,
                'alterado_em' => now(),
            ]);

            $testRows = DB::table('participantes_de_teste')->whereIn('participante_id', $selectedIds)->lockForUpdate()->get();
            if ($testRows->isNotEmpty()) {
                $testKeeper = $testRows->firstWhere('participante_id', $targetId) ?? $testRows->first();
                DB::table('participantes_de_teste')->whereIn('id', $testRows->pluck('id')->reject(fn ($id): bool => (int) $id === (int) $testKeeper->id))->delete();
                DB::table('participantes_de_teste')->where('id', $testKeeper->id)->update([
                    'participante_id' => $targetId,
                    'alterado_em' => now(),
                ]);
            }

            $responsibleRows = DB::table('responsaveis')->whereIn('participante_id', $selectedIds)->lockForUpdate()->get();
            if ($responsibleRows->isNotEmpty()) {
                $responsibleKeeper = $responsibleRows->firstWhere('participante_id', $targetId) ?? $responsibleRows->first();
                $discardResponsibleIds = $responsibleRows->pluck('id')->reject(fn ($id): bool => (int) $id === (int) $responsibleKeeper->id)->values();
                if ($discardResponsibleIds->isNotEmpty()) {
                    DB::table('novos_certificados')->whereIn('responsavel_id', $discardResponsibleIds)->update([
                        'responsavel_id' => $responsibleKeeper->id,
                        'alterado_em' => now(),
                    ]);
                    DB::table('responsaveis')->whereIn('id', $discardResponsibleIds)->delete();
                }
                DB::table('responsaveis')->where('id', $responsibleKeeper->id)->update([
                    'participante_id' => $targetId,
                    'alterado_em' => now(),
                ]);
            }

            $remainingReferences = collect([
                'certificados legados' => DB::table('certificados')->whereIn('participanteId', $sourceIds)->count(),
                'certificados novos' => DB::table('lista_participantes')->whereIn('participante_id', $sourceIds)->count(),
                'responsáveis' => DB::table('responsaveis')->whereIn('participante_id', $sourceIds)->count(),
                'rubricas' => DB::table('rubricas_participantes')->whereIn('participante_id', $sourceIds)->count(),
                'participantes de teste' => DB::table('participantes_de_teste')->whereIn('participante_id', $sourceIds)->count(),
                'assinaturas de template' => DB::table('assinaturas_template')->whereIn('participante_id', $sourceIds)->count(),
            ])->filter();

            if ($remainingReferences->isNotEmpty()) {
                $details = $remainingReferences->map(fn (int $count, string $label): string => "{$count} {$label}")->implode(', ');
                throw ValidationException::withMessages([
                    'selecao' => "A unificação não pode remover os participantes de origem porque ainda existem vínculos: {$details}. Nenhuma alteração foi realizada.",
                ]);
            }

            $removed = DB::table('participantes')
                ->whereIn('id', $sourceIds)
                ->delete();

            $unification = $history->record($request, $before, $targetId, $targetName, $targetCreated);

            return [
                'unificacao_id' => $unification->id,
                'participante_id' => $targetId,
                'participante_nome' => $targetName,
                'certificados_legados_atualizados' => $legacyCertificatesUpdated,
                'certificados_novos_atualizados' => $newCertificatesUpdated,
                'participantes_removidos' => $removed,
            ];
        });

        $request->session()->forget('selecao_participantes');

        return response()->json([
            'message' => 'Participantes unificados com sucesso.',
            ...$result,
        ]);
    }

    public function create(): View
    {
        return view('participantes.form', ['participante' => new Participante()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['criado_por'] = $this->sessionUserId($request);
        $participante = Participante::query()->create($data);

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

    public function toggleStatus(int $id, string $nome): RedirectResponse
    {
        $participante = $this->find($id, $nome);
        $participante->update(['ativo' => ! $participante->ativo]);

        return redirect()->route('participantes.index')
            ->with('status', 'Status do participante atualizado com sucesso.');
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

    private function sessionUserId(Request $request): ?int
    {
        $id = (int) $request->session()->get('gi_context.usuario.id', 0);
        return $id > 0 ? $id : null;
    }

    private function selectedIds(Request $request): array
    {
        return collect((array) $request->session()->get('selecao_participantes', []))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
