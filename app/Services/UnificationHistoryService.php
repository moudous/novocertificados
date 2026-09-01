<?php

namespace App\Services;

use App\Models\UnificacaoRealizada;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UnificationHistoryService
{
    public function capture(array $participantIds): array
    {
        $participants = $this->rows('participantes', 'id', $participantIds);
        $legacy = $this->rows('certificados', 'participanteId', $participantIds);
        $new = $this->rows('lista_participantes', 'participante_id', $participantIds);
        $responsibles = $this->rows('responsaveis', 'participante_id', $participantIds);
        $newIds = collect($new)->pluck('id')->all();
        $responsibleIds = collect($responsibles)->pluck('id')->all();

        return [
            'participantes' => $participants,
            'certificados' => $legacy,
            'lista_participantes' => $new,
            'rubricas_participantes' => $this->rows('rubricas_participantes', 'participante_id', $participantIds),
            'participantes_de_teste' => $this->rows('participantes_de_teste', 'participante_id', $participantIds),
            'assinaturas_template' => $this->rows('assinaturas_template', 'participante_id', $participantIds),
            'responsaveis' => $responsibles,
            'novos_certificados' => DB::table('novos_certificados')
                ->where(fn ($query) => $query->whereIn('lista_participantes_id', $newIds)->orWhereIn('responsavel_id', $responsibleIds))
                ->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
        ];
    }

    public function record(Request $request, array $before, int $targetId, string $targetName, bool $targetCreated): UnificacaoRealizada
    {
        $excluded = collect($before['participantes'])->reject(fn (array $participant): bool => !$targetCreated && (int) $participant['id'] === $targetId)->values();

        return UnificacaoRealizada::query()->create([
            'participante_novo_id' => $targetId,
            'participante_novo_nome' => $targetName,
            'participantes_excluidos' => $excluded->map(fn (array $participant): array => [
                'id' => (int) $participant['id'],
                'nome' => $participant['nome'] ?? null,
                'email' => $participant['email'] ?? null,
            ])->all(),
            'usuario_id' => $this->sessionUserId($request),
            'usuario_nome' => data_get($request->session()->get('gi_context'), 'usuario.nome'),
            'dados_antes' => $before,
            'dados_depois' => [
                'destino_criado' => $targetCreated,
                'participante_destino_id' => $targetId,
                'participante_destino_nome' => $targetName,
            ],
            'status' => 'realizada',
        ]);
    }

    public function undo(UnificacaoRealizada $unification, Request $request): array
    {
        return DB::transaction(function () use ($unification, $request): array {
            $audit = UnificacaoRealizada::query()->whereKey($unification->id)->lockForUpdate()->firstOrFail();
            if ($audit->status !== 'realizada') {
                throw ValidationException::withMessages(['unificacao' => 'Esta unificação já foi desfeita e não pode ser executada novamente.']);
            }

            $before = (array) $audit->dados_antes;
            $excluded = collect((array) $audit->participantes_excluidos);
            $conflicts = $this->participantConflicts($excluded);
            if ($conflicts->isNotEmpty()) return ['conflicts' => $conflicts->all()];

            $targetCreated = (bool) data_get($audit->dados_depois, 'destino_criado', false);
            $targetId = (int) $audit->participante_novo_id;
            $this->restoreParticipants((array) ($before['participantes'] ?? []), $excluded);
            $this->restoreRows('certificados', (array) ($before['certificados'] ?? []));

            $originalNew = collect((array) ($before['lista_participantes'] ?? []));
            $emissionIds = $originalNew->pluck('novo_certificado_id')->filter()->unique()->all();
            if ($emissionIds !== []) {
                DB::table('lista_participantes')->where('participante_id', $targetId)->whereIn('novo_certificado_id', $emissionIds)->delete();
            }
            $this->replaceRowsByIds('lista_participantes', $originalNew->all());

            $this->replaceRowsByIds('participantes_de_teste', (array) ($before['participantes_de_teste'] ?? []));
            $this->replaceRowsByIds('responsaveis', (array) ($before['responsaveis'] ?? []));
            $this->restoreRows('rubricas_participantes', (array) ($before['rubricas_participantes'] ?? []));
            $this->restoreRows('assinaturas_template', (array) ($before['assinaturas_template'] ?? []));
            $this->restoreRows('novos_certificados', (array) ($before['novos_certificados'] ?? []));

            if ($targetCreated) {
                $remaining = DB::table('certificados')->where('participanteId', $targetId)->exists()
                    || DB::table('lista_participantes')->where('participante_id', $targetId)->exists()
                    || DB::table('responsaveis')->where('participante_id', $targetId)->exists()
                    || DB::table('rubricas_participantes')->where('participante_id', $targetId)->exists()
                    || DB::table('participantes_de_teste')->where('participante_id', $targetId)->exists()
                    || DB::table('assinaturas_template')->where('participante_id', $targetId)->exists();
                if ($remaining) {
                    throw ValidationException::withMessages(['unificacao' => 'O participante criado pela unificação recebeu novos vínculos posteriormente. Faça uma intervenção manual antes de desfazer.']);
                }
                DB::table('participantes')->where('id', $targetId)->delete();
            }

            $audit->update([
                'status' => 'desfeita',
                'desfeito_por' => $this->sessionUserId($request),
                'desfeito_em' => now(),
            ]);

            return ['conflicts' => [], 'restored_participants' => $excluded->count()];
        });
    }

    private function participantConflicts(Collection $excluded): Collection
    {
        return $excluded->flatMap(function (array $participant): array {
            $id = (int) $participant['id'];
            $conflicts = [];
            $sameId = DB::table('participantes')->where('id', $id)->first(['id', 'nome', 'email']);
            if ($sameId) {
                $conflicts[] = ['tipo' => 'ID já utilizado', 'original' => $participant, 'existente' => (array) $sameId];
            }
            $email = trim((string) ($participant['email'] ?? ''));
            if ($email !== '') {
                DB::table('participantes')->where('email', $email)->where('id', '<>', $id)->get(['id', 'nome', 'email'])->each(
                    function (object $existing) use (&$conflicts, $participant): void {
                        $conflicts[] = ['tipo' => 'E-mail já utilizado', 'original' => $participant, 'existente' => (array) $existing];
                    }
                );
            }
            return $conflicts;
        })->values();
    }

    private function restoreParticipants(array $rows, Collection $excluded): void
    {
        $excludedIds = $excluded->pluck('id')->map(fn ($id): int => (int) $id)->all();
        foreach ($rows as $row) {
            if (in_array((int) $row['id'], $excludedIds, true)) DB::table('participantes')->insert($row);
            else DB::table('participantes')->where('id', $row['id'])->update(collect($row)->except('id')->all());
        }
    }

    private function replaceRowsByIds(string $table, array $rows): void
    {
        $ids = collect($rows)->pluck('id')->filter()->all();
        if ($ids !== []) DB::table($table)->whereIn('id', $ids)->delete();
        foreach ($rows as $row) DB::table($table)->insert($row);
    }

    private function restoreRows(string $table, array $rows): void
    {
        foreach ($rows as $row) DB::table($table)->updateOrInsert(['id' => $row['id']], collect($row)->except('id')->all());
    }

    private function rows(string $table, string $column, array $ids): array
    {
        return DB::table($table)->whereIn($column, $ids)->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();
    }

    private function sessionUserId(Request $request): ?int
    {
        $id = (int) $request->session()->get('gi_context.usuario.id', 0);
        return $id > 0 ? $id : null;
    }
}
