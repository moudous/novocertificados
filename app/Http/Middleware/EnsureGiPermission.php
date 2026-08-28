<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGiPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        abort_unless($request->session()->has('gi_context'), 401, 'Abra esta aplicação pelo menu do GI.');

        $permissions = (array) $request->session()->get('gi_context.permissoes', []);
        abort_unless(in_array($permission, $permissions, true), 403, 'Você não possui permissão para esta operação.');

        return $next($request);
    }
}
