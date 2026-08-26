<?php

namespace App\Services;

use App\Models\Pessoa;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class GiPessoaSynchronizer
{
    public function syncSessionUser(array $context): Pessoa
    {
        $data = $this->normalize((array) ($context['usuario'] ?? []), (array) ($context['perfil'] ?? []));

        return Pessoa::query()->updateOrCreate(['id' => $data['id']], $data);
    }

    public function syncDirectory(array $users): int
    {
        $now = now();
        $rows = collect($users)
            ->filter(fn ($user): bool => is_array($user))
            ->map(function (array $user) use ($now): array {
                $data = $this->normalize($user, (array) ($user['perfil'] ?? []));

                return [...$data, 'created_at' => $now, 'updated_at' => $now];
            })
            ->values()
            ->all();

        if ($rows === []) {
            return 0;
        }

        DB::transaction(fn () => Pessoa::query()->upsert(
            $rows,
            ['id'],
            ['usuario', 'nome', 'email', 'perfil', 'perfil_id', 'ativo', 'updated_at'],
        ));

        return count($rows);
    }

    private function normalize(array $user, array $profile): array
    {
        $id = filter_var($user['id'] ?? null, FILTER_VALIDATE_INT);
        $name = trim((string) ($user['nome'] ?? ''));
        $email = trim((string) ($user['email'] ?? ''));

        if ($id === false || $id < 1 || $name === '' || $email === '') {
            throw new UnexpectedValueException('O GI retornou uma pessoa sem ID, nome ou e-mail válido.');
        }

        return [
            'id' => $id,
            'usuario' => filled($user['usuario'] ?? null) ? (string) $user['usuario'] : null,
            'nome' => $name,
            'email' => $email,
            'perfil' => filled($profile['nome'] ?? null) ? (string) $profile['nome'] : null,
            'perfil_id' => isset($profile['id']) ? (int) $profile['id'] : null,
            'ativo' => array_key_exists('ativo', $user) ? (bool) $user['ativo'] : true,
        ];
    }
}
