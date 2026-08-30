<?php

namespace App\Http\Controllers;

use App\Models\ListaParticipante;
use App\Services\CertificadoPdfGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CertificadoNovoController extends Controller
{
    private const COLUMNS = ['id', 'id', 'id', 'participante_id', 'id', 'id', 'id', 'id', 'id', 'ativo', 'id'];

    public function index(Request $request): View
    {
        return view('certificadosnovos.certificados.index', ['permissions' => (array) $request->session()->get('gi_context.permissoes', [])]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = ListaParticipante::query()->with(['participante', 'novoCertificado.certificadoAntigo', 'novoCertificado.template', 'novoCertificado.evento', 'novoCertificado.atividade.evento']);
        $total = (clone $query)->count();
        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('id', 'like', "%{$search}%")
                    ->orWhereHas('participante', fn (Builder $item): Builder => $item->where('nome', 'like', "%{$search}%"))
                    ->orWhereHas('novoCertificado', fn (Builder $item): Builder => $item->where('nome', 'like', "%{$search}%")
                        ->orWhereHas('template', fn (Builder $template): Builder => $template->where('nome', 'like', "%{$search}%"))
                        ->orWhereHas('evento', fn (Builder $evento): Builder => $evento->where('nome', 'like', "%{$search}%"))
                        ->orWhereHas('atividade', fn (Builder $atividade): Builder => $atividade->where('nome', 'like', "%{$search}%")));
            });
        }
        $filtered = (clone $query)->count();
        $column = self::COLUMNS[(int) $request->input('order.0.column', 0)] ?? 'id';
        $permissions = (array) $request->session()->get('gi_context.permissoes', []);
        $items = $query->orderBy($column, $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc')
            ->skip(max((int) $request->input('start', 0), 0))->take(min(max((int) $request->input('length', 10), 1), 100))->get();

        return response()->json([
            'draw' => (int) $request->input('draw'), 'recordsTotal' => $total, 'recordsFiltered' => $filtered,
            'data' => $items->map(fn (ListaParticipante $item): array => [
                'id' => $item->id,
                'emissao' => e($item->novoCertificado?->nome ?: '#'.$item->novo_certificado_id),
                'template' => e($item->novoCertificado?->template?->nome ?: '—'),
                'participante' => e($item->participante?->nome ?: '—'),
                'evento' => e($item->novoCertificado?->evento?->nome ?: $item->novoCertificado?->atividade?->evento?->nome ?: '—'),
                'atividade' => e($item->novoCertificado?->atividade?->nome ?: '—'),
                'data_certificado' => $item->novoCertificado?->data_emissao?->format('d/m/Y') ?? '—',
                'horas' => e((string) (data_get($item->snapshot_dados, 'atividade.carga_horaria') ?: data_get($item->novoCertificado?->campos_personalizados, 'carga_horaria') ?: '—')),
                'pdf' => $item->arquivoExists() && filled($item->codigo) ? '<a target="_blank" rel="noopener noreferrer" href="'.e(route('certificadosnovos.public.pdf', $item->codigo)).'" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>' : '—',
                'ativo' => $item->ativo ? '<span class="badge text-bg-success">Ativo</span>' : '<span class="badge text-bg-secondary">Inativo</span>',
                'acoes' => view('certificadosnovos.certificados.actions', ['item' => $item, 'permissions' => $permissions])->render(),
            ]),
        ]);
    }

    public function show(ListaParticipante $item): View
    {
        $item->load(['participante', 'novoCertificado.template', 'novoCertificado.evento', 'novoCertificado.atividade.evento']);
        return view('certificadosnovos.certificados.show', compact('item'));
    }

    public function toggleStatus(ListaParticipante $item): RedirectResponse
    {
        $item->update(['ativo' => ! $item->ativo]);
        return back()->with('status', 'Status do certificado atualizado com sucesso.');
    }

    public function generate(ListaParticipante $item, CertificadoPdfGenerator $generator): RedirectResponse
    {
        try { $generator->generate($item); } catch (\Throwable) { return back()->withErrors(['pdf' => 'Não foi possível gerar o PDF. Consulte o registro de erros.']); }
        return back()->with('status', 'PDF gerado com sucesso.');
    }

    public function pdf(ListaParticipante $item): BinaryFileResponse
    {
        abort_unless($item->arquivoExists(), 404);
        return response()->file($item->arquivoPath());
    }

    public function publicPdf(string $codigo): BinaryFileResponse
    {
        abort_unless(preg_match('/^[A-Za-z0-9-]{6,64}$/', $codigo) === 1, 404);
        $item = ListaParticipante::query()
            ->where('codigo', $codigo)
            ->where('ativo', true)
            ->whereHas('novoCertificado', fn (Builder $query): Builder => $query
                ->where('ativo', true)
                ->whereNull('apagado_em'))
            ->firstOrFail();
        abort_unless($item->arquivoExists(), 404);

        return response()->file($item->arquivoPath(), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="certificado-'.$codigo.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
