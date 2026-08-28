<?php

use App\Http\Controllers\PessoaController;
use App\Http\Controllers\ParticipanteController;
use App\Http\Controllers\EventoController;
use App\Services\GiPessoaSynchronizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::get('/auth/gi', function (Request $request) {
    abort_unless($request->filled('code'), 400, 'Código ausente.');

    $response = Http::asForm()->timeout(10)->post(
        rtrim(config('gi.gi_url'), '/').'/integracoes/gi/trocar-codigo',
        [
            'client_id' => config('gi.client_id'),
            'client_secret' => config('gi.client_secret'),
            'code' => $request->string('code')->toString(),
        ],
    );

    abort_unless($response->successful(), 401, 'Não foi possível autenticar pelo GI.');

    $context = (array) $response->json('data');
    $synchronizer = app(GiPessoaSynchronizer::class);
    $total = $synchronizer->syncFromGi((string) $context['access_token']);
    // O diretório usa o primeiro perfil associado ao usuário. Para quem está
    // entrando agora, prevalece o perfil efetivamente selecionado no GI.
    $synchronizer->syncSessionUser($context);
    $context['atualizacao_usuarios'] = ['realizada' => true, 'total' => $total];

    $request->session()->regenerate();
    $request->session()->put('gi_context', $context);

    $destination = (string) $response->json('data.caminho', '/');
    if (! str_starts_with($destination, '/')
        || str_starts_with($destination, '//')
        || str_contains($destination, '\\')
        || str_contains($destination, '..')) {
        $destination = '/';
    }

    return redirect($destination);
})->name('auth.gi');

Route::get('/', function (Request $request) {
    abort_unless($request->session()->has('gi_context'), 401, 'Abra esta aplicação pelo menu do GI.');

    $visibleContext = $request->session()->get('gi_context');
    unset($visibleContext['access_token']);

    return response()
        ->view('session', ['context' => $visibleContext])
        ->header('Cache-Control', 'no-store');
});

Route::prefix('pessoas')->name('pessoas.')->group(function (): void {
    Route::get('/', [PessoaController::class, 'index'])->name('index');
    Route::get('/dados', [PessoaController::class, 'data'])->name('data');
    Route::post('/importar', [PessoaController::class, 'import'])->name('import');
    Route::get('/{pessoa}', [PessoaController::class, 'show'])->whereNumber('pessoa')->name('show');
});

Route::prefix('participantes')->name('participantes.')->group(function (): void {
    Route::get('/', [ParticipanteController::class, 'index'])->middleware('gi.permission:participantes.listar')->name('index');
    Route::get('/dados', [ParticipanteController::class, 'data'])->middleware('gi.permission:participantes.listar')->name('data');
    Route::get('/criar', [ParticipanteController::class, 'create'])->middleware('gi.permission:participantes.criar')->name('create');
    Route::post('/', [ParticipanteController::class, 'store'])->middleware('gi.permission:participantes.criar')->name('store');
    Route::get('/{id}/{nome}', [ParticipanteController::class, 'show'])->whereNumber('id')->middleware('gi.permission:participantes.visualizar')->name('show');
    Route::get('/{id}/{nome}/editar', [ParticipanteController::class, 'edit'])->whereNumber('id')->middleware('gi.permission:participantes.editar')->name('edit');
    Route::put('/{id}/{nome}', [ParticipanteController::class, 'update'])->whereNumber('id')->middleware('gi.permission:participantes.editar')->name('update');
    Route::patch('/{id}/{nome}/status', [ParticipanteController::class, 'toggleStatus'])->whereNumber('id')->middleware('gi.permission:participantes.editar')->name('status');
    Route::delete('/{id}/{nome}', [ParticipanteController::class, 'destroy'])->whereNumber('id')->middleware('gi.permission:participantes.excluir')->name('destroy');
    Route::patch('/{id}/{nome}/restaurar', [ParticipanteController::class, 'restore'])->whereNumber('id')->middleware('gi.permission:participantes.restaurar')->name('restore');
    Route::delete('/{id}/{nome}/definitivo', [ParticipanteController::class, 'forceDestroy'])->whereNumber('id')->middleware('gi.permission:participantes.excluir_definitivamente')->name('force-destroy');
});

Route::prefix('eventos')->name('eventos.')->group(function (): void {
    Route::get('/', [EventoController::class, 'index'])->middleware('gi.permission:eventos.listar')->name('index');
    Route::get('/dados', [EventoController::class, 'data'])->middleware('gi.permission:eventos.listar')->name('data');
    Route::get('/criar', [EventoController::class, 'create'])->middleware('gi.permission:eventos.criar')->name('create');
    Route::post('/', [EventoController::class, 'store'])->middleware('gi.permission:eventos.criar')->name('store');
    Route::get('/{evento}', [EventoController::class, 'show'])->whereNumber('evento')->middleware('gi.permission:eventos.visualizar')->name('show');
    Route::get('/{evento}/editar', [EventoController::class, 'edit'])->whereNumber('evento')->middleware('gi.permission:eventos.editar')->name('edit');
    Route::put('/{evento}', [EventoController::class, 'update'])->whereNumber('evento')->middleware('gi.permission:eventos.editar')->name('update');
    Route::delete('/{evento}', [EventoController::class, 'destroy'])->whereNumber('evento')->middleware('gi.permission:eventos.excluir')->name('destroy');
    Route::patch('/{evento}/status', [EventoController::class, 'toggleStatus'])->whereNumber('evento')->middleware('gi.permission:eventos.editar')->name('status');
    Route::patch('/{evento}/restaurar', [EventoController::class, 'restore'])->whereNumber('evento')->middleware('gi.permission:eventos.restaurar')->name('restore');
    Route::delete('/{evento}/definitivo', [EventoController::class, 'forceDestroy'])->whereNumber('evento')->middleware('gi.permission:eventos.excluir_definitivamente')->name('force-destroy');
});

Route::post('/manutencao/{acao}', function (Request $request, string $acao) {
    abort_unless($request->session()->has('gi_context'), 401);
    $comandos = ['optimize-clear' => 'optimize:clear', 'config-cache' => 'config:cache'];
    abort_unless(isset($comandos[$acao]), 404);

    $codigo = Artisan::call($comandos[$acao]);
    $mensagem = $codigo === 0
        ? "Comando php artisan {$comandos[$acao]} executado com sucesso."
        : "O comando php artisan {$comandos[$acao]} terminou com código {$codigo}.";

    return redirect('/')->with('manutencao', $mensagem);
})->name('manutencao.executar');

Route::get('/gi/{resource}', function (Request $request, string $resource) {
    abort_unless($request->session()->has('gi_context'), 401);
    abort_unless(in_array($resource, ['perfis', 'usuarios', 'grupos'], true), 404);

    $upstreamResponse = Http::withToken($request->session()->get('gi_context.access_token'))
        ->acceptJson()->timeout(10)
        ->get(rtrim(config('gi.gi_url'), '/').'/api/integracoes/v1/'.$resource);

    return response($upstreamResponse->body(), $upstreamResponse->status())
        ->header(
            'Content-Type',
            $upstreamResponse->header('Content-Type') ?? 'application/json',
        );
});
