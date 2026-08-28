<?php

namespace App\Services;

use App\Models\Pessoa;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

class GiPessoaSynchronizer
{
    public function syncFromGi(string $accessToken): int
    {
        $response = Http::withToken($accessToken)->acceptJson()->timeout(30)
            ->get(rtrim(config('gi.gi_url'), '/').'/api/integracoes/v1/usuarios');

        abort_unless($response->successful(), 502, 'Não foi possível importar as pessoas do GI.');

        return $this->syncDirectory((array) $response->json('data', []));
    }

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
                $profile = (array) ($user['perfil'] ?? ($user['perfis'][0] ?? []));
                $data = $this->normalize($user, $profile);
                $data['ultimo_acesso'] ??= null;

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
            ['usuario', 'nome', 'email', 'perfil', 'perfil_id', 'ativo', 'ultimo_acesso', 'updated_at'],
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

        $data = [
            'id' => $id,
            'usuario' => filled($user['usuario'] ?? null) ? (string) $user['usuario'] : null,
            'nome' => $name,
            'email' => $email,
            'perfil' => filled($profile['nome'] ?? null) ? (string) $profile['nome'] : null,
            'perfil_id' => isset($profile['id']) ? (int) $profile['id'] : null,
            'ativo' => array_key_exists('ativo', $user) ? (bool) $user['ativo'] : true,
        ];

        // O contexto de login pode não possuir este campo. Nesse caso, preserva
        // o valor já obtido anteriormente pelo diretório de usuários do GI.
        if (array_key_exists('ultimo_acesso', $user)) {
            $data['ultimo_acesso'] = filled($user['ultimo_acesso'])
                ? CarbonImmutable::parse((string) $user['ultimo_acesso'])
                    ->setTimezone((string) config('app.timezone'))
                    ->format('Y-m-d H:i:s')
                : null;
        }

        return $data;
    }
}
