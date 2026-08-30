<?php

use App\Http\Controllers\PessoaController;
use App\Http\Controllers\ParticipanteController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\AtividadeController;
use App\Http\Controllers\CertificadoController;
use App\Http\Controllers\CertificadoA1Controller;
use App\Http\Controllers\VariavelController;
use App\Http\Controllers\RubricaParticipanteController;
use App\Http\Controllers\ParticipanteTesteController;
use App\Http\Controllers\ResponsavelController;
use App\Http\Controllers\BibliotecaImagemController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\AssinaturaTemplateController;
use App\Http\Controllers\NovoCertificadoController;
use App\Http\Controllers\CertificadoNovoController;
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
    Route::post('/selecao', [ParticipanteController::class, 'selection'])->middleware('gi.permission:participantes.listar')->name('selection');
    Route::delete('/selecao', [ParticipanteController::class, 'clearSelection'])->middleware('gi.permission:participantes.listar')->name('selection.clear');
    Route::get('/unificacao', [ParticipanteController::class, 'mergeData'])->middleware(['gi.permission:participantes.editar', 'gi.permission:participantes.excluir_definitivamente', 'gi.permission:certificados.editar'])->name('merge.data');
    Route::post('/unificacao', [ParticipanteController::class, 'merge'])->middleware(['gi.permission:participantes.editar', 'gi.permission:participantes.excluir_definitivamente', 'gi.permission:certificados.editar'])->name('merge');
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

Route::prefix('atividades')->name('atividades.')->group(function (): void {
    Route::get('/', [AtividadeController::class, 'index'])->middleware('gi.permission:atividades.listar')->name('index');
    Route::get('/dados', [AtividadeController::class, 'data'])->middleware('gi.permission:atividades.listar')->name('data');
    Route::get('/eventos', [AtividadeController::class, 'eventos'])->name('eventos');
    Route::get('/criar', [AtividadeController::class, 'create'])->middleware('gi.permission:atividades.criar')->name('create');
    Route::post('/', [AtividadeController::class, 'store'])->middleware('gi.permission:atividades.criar')->name('store');
    Route::get('/{atividade}', [AtividadeController::class, 'show'])->whereNumber('atividade')->middleware('gi.permission:atividades.visualizar')->name('show');
    Route::get('/{atividade}/editar', [AtividadeController::class, 'edit'])->whereNumber('atividade')->middleware('gi.permission:atividades.editar')->name('edit');
    Route::put('/{atividade}', [AtividadeController::class, 'update'])->whereNumber('atividade')->middleware('gi.permission:atividades.editar')->name('update');
    Route::patch('/{atividade}/status', [AtividadeController::class, 'toggleStatus'])->whereNumber('atividade')->middleware('gi.permission:atividades.editar')->name('status');
    Route::delete('/{atividade}', [AtividadeController::class, 'destroy'])->whereNumber('atividade')->middleware('gi.permission:atividades.excluir')->name('destroy');
    Route::patch('/{atividade}/restaurar', [AtividadeController::class, 'restore'])->whereNumber('atividade')->middleware('gi.permission:atividades.restaurar')->name('restore');
    Route::delete('/{atividade}/definitivo', [AtividadeController::class, 'forceDestroy'])->whereNumber('atividade')->middleware('gi.permission:atividades.excluir_definitivamente')->name('force-destroy');
});

Route::prefix('certificados')->name('certificados.')->group(function (): void {
    Route::get('/', [CertificadoController::class, 'index'])->middleware('gi.permission:certificados.listar')->name('index');
    Route::get('/dados', [CertificadoController::class, 'data'])->middleware('gi.permission:certificados.listar')->name('data');
    Route::get('/participantes', [CertificadoController::class, 'participantes'])->name('participantes');
    Route::get('/atividades', [CertificadoController::class, 'atividades'])->name('atividades');
    Route::get('/criar', [CertificadoController::class, 'create'])->middleware('gi.permission:certificados.criar')->name('create');
    Route::post('/', [CertificadoController::class, 'store'])->middleware('gi.permission:certificados.criar')->name('store');
    Route::get('/{certificado}', [CertificadoController::class, 'show'])->whereNumber('certificado')->middleware('gi.permission:certificados.visualizar')->name('show');
    Route::get('/{certificado}/editar', [CertificadoController::class, 'edit'])->whereNumber('certificado')->middleware('gi.permission:certificados.editar')->name('edit');
    Route::put('/{certificado}', [CertificadoController::class, 'update'])->whereNumber('certificado')->middleware('gi.permission:certificados.editar')->name('update');
    Route::patch('/{certificado}/status', [CertificadoController::class, 'toggleStatus'])->whereNumber('certificado')->middleware('gi.permission:certificados.editar')->name('status');
    Route::delete('/{certificado}', [CertificadoController::class, 'destroy'])->whereNumber('certificado')->middleware('gi.permission:certificados.excluir')->name('destroy');
    Route::patch('/{certificado}/restaurar', [CertificadoController::class, 'restore'])->whereNumber('certificado')->middleware('gi.permission:certificados.restaurar')->name('restore');
    Route::delete('/{certificado}/definitivo', [CertificadoController::class, 'forceDestroy'])->whereNumber('certificado')->middleware('gi.permission:certificados.excluir_definitivamente')->name('force-destroy');
});

Route::prefix('certificados_a1')->name('certificados_a1.')->group(function (): void {
    Route::get('/', [CertificadoA1Controller::class, 'index'])->middleware('gi.permission:certificados_a1.listar')->name('index');
    Route::get('/dados', [CertificadoA1Controller::class, 'data'])->middleware('gi.permission:certificados_a1.listar')->name('data');
    Route::get('/criar', [CertificadoA1Controller::class, 'create'])->middleware('gi.permission:certificados_a1.criar')->name('create');
    Route::post('/', [CertificadoA1Controller::class, 'store'])->middleware('gi.permission:certificados_a1.criar')->name('store');
    Route::get('/{certificado}', [CertificadoA1Controller::class, 'show'])->whereNumber('certificado')->middleware('gi.permission:certificados_a1.visualizar')->name('show');
    Route::get('/{certificado}/editar', [CertificadoA1Controller::class, 'edit'])->whereNumber('certificado')->middleware('gi.permission:certificados_a1.editar')->name('edit');
    Route::put('/{certificado}', [CertificadoA1Controller::class, 'update'])->whereNumber('certificado')->middleware('gi.permission:certificados_a1.editar')->name('update');
    Route::delete('/{certificado}', [CertificadoA1Controller::class, 'destroy'])->whereNumber('certificado')->middleware('gi.permission:certificados_a1.excluir')->name('destroy');
    Route::delete('/{certificado}/definitivo', [CertificadoA1Controller::class, 'forceDestroy'])->whereNumber('certificado')->middleware('gi.permission:certificados_a1.excluir_definitivamente')->name('force-destroy');
});

Route::prefix('variaveis')->name('variaveis.')->group(function (): void {
    Route::get('/', [VariavelController::class, 'index'])->middleware('gi.permission:variaveis.listar')->name('index');
    Route::get('/dados', [VariavelController::class, 'data'])->middleware('gi.permission:variaveis.listar')->name('data');
    Route::get('/criar', [VariavelController::class, 'create'])->middleware('gi.permission:variaveis.criar')->name('create');
    Route::post('/', [VariavelController::class, 'store'])->middleware('gi.permission:variaveis.criar')->name('store');
    Route::get('/{variavel}', [VariavelController::class, 'show'])->whereNumber('variavel')->middleware('gi.permission:variaveis.visualizar')->name('show');
    Route::get('/{variavel}/editar', [VariavelController::class, 'edit'])->whereNumber('variavel')->middleware('gi.permission:variaveis.editar')->name('edit');
    Route::put('/{variavel}', [VariavelController::class, 'update'])->whereNumber('variavel')->middleware('gi.permission:variaveis.editar')->name('update');
    Route::patch('/{variavel}/status', [VariavelController::class, 'toggleStatus'])->whereNumber('variavel')->middleware('gi.permission:variaveis.editar')->name('status');
    Route::delete('/{variavel}', [VariavelController::class, 'destroy'])->whereNumber('variavel')->middleware('gi.permission:variaveis.excluir')->name('destroy');
    Route::delete('/{variavel}/definitivo', [VariavelController::class, 'forceDestroy'])->whereNumber('variavel')->middleware('gi.permission:variaveis.excluir_definitivamente')->name('force-destroy');
});

Route::prefix('rubricas_participantes')->name('rubricas_participantes.')->group(function (): void {
    Route::get('/', [RubricaParticipanteController::class, 'index'])->middleware('gi.permission:rubricas_participantes.listar')->name('index');
    Route::get('/dados', [RubricaParticipanteController::class, 'data'])->middleware('gi.permission:rubricas_participantes.listar')->name('data');
    Route::get('/participantes', [RubricaParticipanteController::class, 'participantes'])->name('participantes');
    Route::get('/criar', [RubricaParticipanteController::class, 'create'])->middleware('gi.permission:rubricas_participantes.criar')->name('create');
    Route::post('/', [RubricaParticipanteController::class, 'store'])->middleware('gi.permission:rubricas_participantes.criar')->name('store');
    Route::get('/{rubrica}', [RubricaParticipanteController::class, 'show'])->whereNumber('rubrica')->middleware('gi.permission:rubricas_participantes.visualizar')->name('show');
    Route::get('/{rubrica}/editar', [RubricaParticipanteController::class, 'edit'])->whereNumber('rubrica')->middleware('gi.permission:rubricas_participantes.editar')->name('edit');
    Route::put('/{rubrica}', [RubricaParticipanteController::class, 'update'])->whereNumber('rubrica')->middleware('gi.permission:rubricas_participantes.editar')->name('update');
    Route::patch('/{rubrica}/status', [RubricaParticipanteController::class, 'toggleStatus'])->whereNumber('rubrica')->middleware('gi.permission:rubricas_participantes.editar')->name('status');
    Route::delete('/{rubrica}', [RubricaParticipanteController::class, 'destroy'])->whereNumber('rubrica')->middleware('gi.permission:rubricas_participantes.excluir')->name('destroy');
    Route::delete('/{rubrica}/definitivo', [RubricaParticipanteController::class, 'forceDestroy'])->whereNumber('rubrica')->middleware('gi.permission:rubricas_participantes.excluir_definitivamente')->name('force-destroy');
});

Route::prefix('responsaveis')->name('responsaveis.')->group(function (): void {
    Route::get('/', [ResponsavelController::class, 'index'])->middleware('gi.permission:responsaveis.listar')->name('index');
    Route::get('/dados', [ResponsavelController::class, 'data'])->middleware('gi.permission:responsaveis.listar')->name('data');
    Route::get('/participantes', [ResponsavelController::class, 'participantes'])->name('participantes');
    Route::get('/criar', [ResponsavelController::class, 'create'])->middleware('gi.permission:responsaveis.criar')->name('create');
    Route::post('/', [ResponsavelController::class, 'store'])->middleware('gi.permission:responsaveis.criar')->name('store');
    Route::get('/{responsavel}', [ResponsavelController::class, 'show'])->whereNumber('responsavel')->middleware('gi.permission:responsaveis.visualizar')->name('show');
    Route::get('/{responsavel}/editar', [ResponsavelController::class, 'edit'])->whereNumber('responsavel')->middleware('gi.permission:responsaveis.editar')->name('edit');
    Route::put('/{responsavel}', [ResponsavelController::class, 'update'])->whereNumber('responsavel')->middleware('gi.permission:responsaveis.editar')->name('update');
    Route::patch('/{responsavel}/status', [ResponsavelController::class, 'toggleStatus'])->whereNumber('responsavel')->middleware('gi.permission:responsaveis.editar')->name('status');
    Route::delete('/{responsavel}', [ResponsavelController::class, 'destroy'])->whereNumber('responsavel')->middleware('gi.permission:responsaveis.excluir')->name('destroy');
    Route::patch('/{responsavel}/restaurar', [ResponsavelController::class, 'restore'])->whereNumber('responsavel')->middleware('gi.permission:responsaveis.restaurar')->name('restore');
    Route::delete('/{responsavel}/definitivo', [ResponsavelController::class, 'forceDestroy'])->whereNumber('responsavel')->middleware('gi.permission:responsaveis.excluir_definitivamente')->name('force-destroy');
});

Route::prefix('participantes_teste')->name('participantes_teste.')->group(function (): void {
    Route::get('/', [ParticipanteTesteController::class, 'index'])->middleware('gi.permission:participantes_teste.listar')->name('index');
    Route::get('/dados', [ParticipanteTesteController::class, 'data'])->middleware('gi.permission:participantes_teste.listar')->name('data');
    Route::get('/participantes', [ParticipanteTesteController::class, 'participantes'])->name('participantes');
    Route::get('/criar', [ParticipanteTesteController::class, 'create'])->middleware('gi.permission:participantes_teste.criar')->name('create');
    Route::post('/', [ParticipanteTesteController::class, 'store'])->middleware('gi.permission:participantes_teste.criar')->name('store');
    Route::get('/{registro}', [ParticipanteTesteController::class, 'show'])->whereNumber('registro')->middleware('gi.permission:participantes_teste.visualizar')->name('show');
    Route::get('/{registro}/editar', [ParticipanteTesteController::class, 'edit'])->whereNumber('registro')->middleware('gi.permission:participantes_teste.editar')->name('edit');
    Route::put('/{registro}', [ParticipanteTesteController::class, 'update'])->whereNumber('registro')->middleware('gi.permission:participantes_teste.editar')->name('update');
    Route::delete('/{registro}', [ParticipanteTesteController::class, 'destroy'])->whereNumber('registro')->middleware('gi.permission:participantes_teste.excluir')->name('destroy');
    Route::delete('/{registro}/definitivo', [ParticipanteTesteController::class, 'forceDestroy'])->whereNumber('registro')->middleware('gi.permission:participantes_teste.excluir_definitivamente')->name('force-destroy');
});

Route::prefix('templates')->name('templates.')->group(function (): void {
    Route::get('/', [TemplateController::class, 'index'])->middleware('gi.permission:template.listar')->name('index');
    Route::get('/dados', [TemplateController::class, 'data'])->middleware('gi.permission:template.listar')->name('data');
    Route::get('/certificados-a1', [TemplateController::class, 'certificadosA1'])->name('certificados-a1');
    Route::get('/criar', [TemplateController::class, 'create'])->middleware('gi.permission:template.criar')->name('create');
    Route::post('/', [TemplateController::class, 'store'])->middleware('gi.permission:template.criar')->name('store');
    Route::get('/{template}/construtor', [TemplateController::class, 'builder'])->whereNumber('template')->middleware('gi.permission:template.editar')->name('builder');
    Route::put('/{template}/construtor', [TemplateController::class, 'saveBuilder'])->whereNumber('template')->middleware('gi.permission:template.editar')->name('builder.save');
    Route::post('/{template}/construtor/preview-pdf', [TemplateController::class, 'previewPdf'])->whereNumber('template')->middleware('gi.permission:template.editar')->name('builder.preview');
    Route::post('/{template}/construtor/fontes', [TemplateController::class, 'uploadFont'])->whereNumber('template')->middleware('gi.permission:template.editar')->name('builder.fonts.store');
    Route::get('/{template}', [TemplateController::class, 'show'])->whereNumber('template')->middleware('gi.permission:template.visualizar')->name('show');
    Route::get('/{template}/editar', [TemplateController::class, 'edit'])->whereNumber('template')->middleware('gi.permission:template.editar')->name('edit');
    Route::put('/{template}', [TemplateController::class, 'update'])->whereNumber('template')->middleware('gi.permission:template.editar')->name('update');
    Route::patch('/{template}/status', [TemplateController::class, 'toggleStatus'])->whereNumber('template')->middleware('gi.permission:template.editar')->name('status');
    Route::delete('/{template}', [TemplateController::class, 'destroy'])->whereNumber('template')->middleware('gi.permission:template.excluir')->name('destroy');
    Route::delete('/{template}/definitivo', [TemplateController::class, 'forceDestroy'])->whereNumber('template')->middleware('gi.permission:template.excluir_definitivamente')->name('force-destroy');
});

Route::prefix('biblioteca-imagens')->name('biblioteca_imagens.')->group(function (): void {
    Route::get('/',[BibliotecaImagemController::class,'index'])->middleware('gi.permission:biblioteca_imagens.listar')->name('index');
    Route::get('/dados',[BibliotecaImagemController::class,'data'])->middleware('gi.permission:biblioteca_imagens.listar')->name('data');
    Route::get('/criar',[BibliotecaImagemController::class,'create'])->middleware('gi.permission:biblioteca_imagens.criar')->name('create');
    Route::post('/',[BibliotecaImagemController::class,'store'])->middleware('gi.permission:biblioteca_imagens.criar')->name('store');
    Route::get('/{imagem}/editar',[BibliotecaImagemController::class,'edit'])->middleware('gi.permission:biblioteca_imagens.editar')->name('edit');
    Route::put('/{imagem}',[BibliotecaImagemController::class,'update'])->middleware('gi.permission:biblioteca_imagens.editar')->name('update');
    Route::patch('/{imagem}/status',[BibliotecaImagemController::class,'toggle'])->middleware('gi.permission:biblioteca_imagens.editar')->name('status');
    Route::delete('/{imagem}',[BibliotecaImagemController::class,'destroy'])->middleware('gi.permission:biblioteca_imagens.excluir')->name('destroy');
    Route::patch('/{imagem}/restaurar',[BibliotecaImagemController::class,'restore'])->whereNumber('imagem')->middleware('gi.permission:biblioteca_imagens.restaurar')->name('restore');
    Route::delete('/{imagem}/definitivo',[BibliotecaImagemController::class,'forceDestroy'])->whereNumber('imagem')->middleware('gi.permission:biblioteca_imagens.excluir_definitivamente')->name('force-destroy');
});

Route::prefix('assinaturas_template')->name('assinaturas_template.')->group(function (): void {
    Route::get('/',[AssinaturaTemplateController::class,'index'])->middleware('gi.permission:assinaturas.listar')->name('index');
    Route::get('/dados',[AssinaturaTemplateController::class,'data'])->middleware('gi.permission:assinaturas.listar')->name('data');
    Route::get('/participantes',[AssinaturaTemplateController::class,'participantes'])->name('participantes');
    Route::get('/templates',[AssinaturaTemplateController::class,'templates'])->name('templates');
    Route::get('/criar',[AssinaturaTemplateController::class,'create'])->middleware('gi.permission:assinaturas.criar')->name('create');
    Route::post('/',[AssinaturaTemplateController::class,'store'])->middleware('gi.permission:assinaturas.criar')->name('store');
    Route::get('/{assinatura}',[AssinaturaTemplateController::class,'show'])->whereNumber('assinatura')->middleware('gi.permission:assinaturas.visualizar')->name('show');
    Route::get('/{assinatura}/editar',[AssinaturaTemplateController::class,'edit'])->whereNumber('assinatura')->middleware('gi.permission:assinaturas.editar')->name('edit');
    Route::put('/{assinatura}',[AssinaturaTemplateController::class,'update'])->whereNumber('assinatura')->middleware('gi.permission:assinaturas.editar')->name('update');
    Route::patch('/{assinatura}/status',[AssinaturaTemplateController::class,'toggleStatus'])->whereNumber('assinatura')->middleware('gi.permission:assinaturas.editar')->name('status');
    Route::delete('/{assinatura}',[AssinaturaTemplateController::class,'destroy'])->whereNumber('assinatura')->middleware('gi.permission:assinaturas.excluir')->name('destroy');
    Route::delete('/{assinatura}/definitivo',[AssinaturaTemplateController::class,'forceDestroy'])->whereNumber('assinatura')->middleware('gi.permission:assinaturas.excluir_definitivamente')->name('force-destroy');
});

Route::prefix('emissoes')->name('emissoes.')->group(function (): void {
    Route::get('/',[NovoCertificadoController::class,'index'])->middleware('gi.permission:emissoes.listar')->name('index');
    Route::get('/dados',[NovoCertificadoController::class,'data'])->middleware('gi.permission:emissoes.listar')->name('data');
    Route::get('/certificados',[NovoCertificadoController::class,'certificados'])->name('certificados');
    Route::get('/templates',[NovoCertificadoController::class,'templates'])->name('templates');
    Route::get('/atividades',[NovoCertificadoController::class,'activities'])->name('activities');
    Route::get('/criar',[NovoCertificadoController::class,'create'])->middleware('gi.permission:emissoes.criar')->name('create');
    Route::post('/',[NovoCertificadoController::class,'store'])->middleware('gi.permission:emissoes.criar')->name('store');
    Route::get('/{certificado}/participantes/opcoes',[NovoCertificadoController::class,'participantOptions'])->whereNumber('certificado')->name('participantes.opcoes');
    Route::get('/{certificado}/participantes',[NovoCertificadoController::class,'participants'])->whereNumber('certificado')->middleware('gi.permission:emissoes.participantes')->name('participantes');
    Route::post('/{certificado}/participantes',[NovoCertificadoController::class,'addParticipants'])->whereNumber('certificado')->middleware('gi.permission:emissoes.inserir_participantes')->name('participantes.store');
    Route::post('/{certificado}/gerar',[NovoCertificadoController::class,'generate'])->whereNumber('certificado')->middleware('gi.permission:emissoes.gerar_pdfs')->name('generate');
    Route::get('/{certificado}/participantes/{item}/pdf',[NovoCertificadoController::class,'pdf'])->whereNumber(['certificado','item'])->middleware('gi.permission:emissoes.visualizar')->name('participantes.pdf');
    Route::delete('/{certificado}/participantes/{item}',[NovoCertificadoController::class,'removeParticipant'])->whereNumber(['certificado','item'])->middleware('gi.permission:emissoes.excluir_participantes')->name('participantes.destroy');
    Route::get('/{certificado}',[NovoCertificadoController::class,'show'])->whereNumber('certificado')->middleware('gi.permission:emissoes.visualizar')->name('show');
    Route::get('/{certificado}/editar',[NovoCertificadoController::class,'edit'])->whereNumber('certificado')->middleware('gi.permission:emissoes.editar')->name('edit');
    Route::put('/{certificado}',[NovoCertificadoController::class,'update'])->whereNumber('certificado')->middleware('gi.permission:emissoes.editar')->name('update');
    Route::patch('/{certificado}/status',[NovoCertificadoController::class,'toggleStatus'])->whereNumber('certificado')->middleware('gi.permission:emissoes.ativar_desativar')->name('status');
    Route::delete('/{certificado}',[NovoCertificadoController::class,'destroy'])->whereNumber('certificado')->middleware('gi.permission:emissoes.excluir')->name('destroy');
    Route::delete('/{certificado}/definitivo',[NovoCertificadoController::class,'forceDestroy'])->whereNumber('certificado')->middleware('gi.permission:emissoes.excluir_definitivamente')->name('force-destroy');
});

Route::prefix('certificadosnovos')->name('certificadosnovos.')->group(function (): void {
    Route::get('/', [CertificadoNovoController::class, 'index'])->middleware('gi.permission:certificadosnovos.listar')->name('index');
    Route::get('/dados', [CertificadoNovoController::class, 'data'])->middleware('gi.permission:certificadosnovos.listar')->name('data');
    Route::get('/{item}', [CertificadoNovoController::class, 'show'])->whereNumber('item')->middleware('gi.permission:certificadosnovos.visualizar')->name('show');
    Route::get('/{item}/pdf', [CertificadoNovoController::class, 'pdf'])->whereNumber('item')->middleware('gi.permission:certificadosnovos.visualizar')->name('pdf');
    Route::post('/{item}/gerar-pdf', [CertificadoNovoController::class, 'generate'])->whereNumber('item')->middleware('gi.permission:certificadosnovos.gerar_pdf')->name('generate');
    Route::patch('/{item}/status', [CertificadoNovoController::class, 'toggleStatus'])->whereNumber('item')->middleware('gi.permission:certificadosnovos.ativar_desativar')->name('status');
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
