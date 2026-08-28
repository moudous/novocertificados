<?php

namespace App\Http\Controllers;

use App\Models\CertificadoA1;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CertificadoA1Controller extends Controller
{
    private const COLUMNS = ['id', 'nome', 'criado_em', 'alterado_em'];

    public function index(Request $request): View
    {
        return view('certificados_a1.index', [
            'permissions' => (array) $request->session()->get('gi_context.permissoes', []),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = CertificadoA1::withTrashed();
        $recordsTotal = (clone $query)->count();
        $search = trim((string) $request->input('search.value', ''));

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('nome', 'like', "%{$search}%")
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
            ->map(fn (CertificadoA1 $certificado): array => [
                'id' => $certificado->id,
                'nome' => e($certificado->nome ?: '—'),
                'criado_em' => $certificado->criado_em?->format('d/m/Y H:i') ?? '—',
                'alterado_em' => $certificado->alterado_em?->format('d/m/Y H:i') ?? '—',
                'acoes' => view('certificados_a1.partials.actions', [
                    'certificado' => $certificado,
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
        return view('certificados_a1.form', ['certificado' => new CertificadoA1()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $certificado = CertificadoA1::query()->create($this->validated($request));

        return redirect()->route('certificados_a1.show', $certificado)
            ->with('status', 'Certificado A1 cadastrado com sucesso.');
    }

    public function show(CertificadoA1 $certificado): View
    {
        return view('certificados_a1.show', compact('certificado'));
    }

    public function edit(CertificadoA1 $certificado): View
    {
        return view('certificados_a1.form', compact('certificado'));
    }

    public function update(Request $request, CertificadoA1 $certificado): RedirectResponse
    {
        $certificado->update($this->validated($request));

        return redirect()->route('certificados_a1.show', $certificado)
            ->with('status', 'Certificado A1 atualizado com sucesso.');
    }

    public function destroy(CertificadoA1 $certificado): RedirectResponse
    {
        $certificado->delete();

        return redirect()->route('certificados_a1.index')
            ->with('status', 'Certificado A1 excluído com sucesso.');
    }

    public function forceDestroy(int $certificado): RedirectResponse
    {
        CertificadoA1::withTrashed()->findOrFail($certificado)->forceDelete();

        return redirect()->route('certificados_a1.index')
            ->with('status', 'Certificado A1 excluído definitivamente.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nome' => ['nullable', 'string', 'max:50'],
        ]);
    }
}
