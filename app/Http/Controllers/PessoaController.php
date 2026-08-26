<?php

namespace App\Http\Controllers;

use App\Models\Pessoa;
use App\Services\GiPessoaSynchronizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PessoaController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeGiSession($request);

        return view('pessoas.index', [
            'pessoas' => Pessoa::query()->orderBy('nome')->get(),
        ]);
    }

    public function show(Request $request, Pessoa $pessoa): View
    {
        $this->authorizeGiSession($request);

        return view('pessoas.show', compact('pessoa'));
    }

    public function import(Request $request, GiPessoaSynchronizer $synchronizer): JsonResponse
    {
        $this->authorizeGiSession($request);

        $accessToken = (string) $request->session()->get('gi_context.access_token', '');
        abort_if($accessToken === '', 401, 'Token de acesso do GI não encontrado. Abra novamente pelo menu do GI.');

        $total = $synchronizer->syncFromGi($accessToken);

        return response()->json([
            'message' => "$total pessoa(s) importada(s) com sucesso.",
            'total' => $total,
        ]);
    }

    private function authorizeGiSession(Request $request): void
    {
        abort_unless($request->session()->has('gi_context'), 401, 'Abra esta aplicação pelo menu do GI.');
    }
}
