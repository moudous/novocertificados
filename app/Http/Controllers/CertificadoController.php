<?php

namespace App\Http\Controllers;

use App\Models\Atividade;
use App\Models\Certificado;
use App\Models\Participante;
use App\Support\LegacyPdfHtml;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CertificadoController extends Controller
{
    public mixed $pdf = null;
    private const COLUMNS = ['id', 'nome', 'participanteId', 'atividadeId', 'tipo', 'cargaHoraria', 'ativo', 'criado_em', 'atualizado_em'];

    public function index(Request $request): View
    {
        return view('certificados.index', [
            'permissions' => (array) $request->session()->get('gi_context.permissoes', []),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = Certificado::withTrashed()->with(['participante', 'atividade']);
        $recordsTotal = (clone $query)->count();
        $search = trim((string) $request->input('search.value', ''));

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('nome', 'like', "%{$search}%")
                    ->orWhere('titulo', 'like', "%{$search}%")
                    ->orWhere('tipo', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%")
                    ->orWhereHas('participante', fn (Builder $participant): Builder => $participant->where('nome', 'like', "%{$search}%"))
                    ->orWhereHas('atividade', fn (Builder $activity): Builder => $activity->where('nome', 'like', "%{$search}%"));
            });
        }

        $id = trim((string) $request->input('filters.id', ''));
        $name = trim((string) $request->input('filters.nome', ''));
        $participantId = trim((string) $request->input('filters.participanteId', ''));
        $activityId = trim((string) $request->input('filters.atividadeId', ''));
        $type = trim((string) $request->input('filters.tipo', ''));
        $workload = trim((string) $request->input('filters.cargaHoraria', ''));
        $status = trim((string) $request->input('filters.status', ''));
        $createdAt = trim((string) $request->input('filters.criado_em', ''));

        $query
            ->when($id !== '' && ctype_digit($id), fn (Builder $query): Builder => $query->where('id', (int) $id))
            ->when($name !== '', fn (Builder $query): Builder => $query->where('nome', 'like', "%{$name}%"))
            ->when($participantId !== '' && ctype_digit($participantId), fn (Builder $query): Builder => $query->where('participanteId', (int) $participantId))
            ->when($activityId !== '' && ctype_digit($activityId), fn (Builder $query): Builder => $query->where('atividadeId', (int) $activityId))
            ->when($type !== '', fn (Builder $query): Builder => $query->where('tipo', 'like', "%{$type}%"))
            ->when($workload !== '' && ctype_digit($workload), fn (Builder $query): Builder => $query->where('cargaHoraria', (int) $workload))
            ->when($status === 'ativo', fn (Builder $query): Builder => $query->whereNull('apagado_em')->where('ativo', true))
            ->when($status === 'inativo', fn (Builder $query): Builder => $query->whereNull('apagado_em')->where('ativo', false))
            ->when($status === 'excluido', fn (Builder $query): Builder => $query->whereNotNull('apagado_em'))
            ->when(
                preg_match('/^\d{4}-\d{2}-\d{2}$/', $createdAt) === 1,
                fn (Builder $query): Builder => $query->whereDate('criado_em', $createdAt)
            );

        $recordsFiltered = (clone $query)->count();
        $column = self::COLUMNS[(int) $request->input('order.0.column', 0)] ?? 'id';
        $direction = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';
        $length = min(max((int) $request->input('length', 10), 1), 100);
        $start = max((int) $request->input('start', 0), 0);
        $permissions = (array) $request->session()->get('gi_context.permissoes', []);

        $data = $query->orderBy($column, $direction)->skip($start)->take($length)->get()
            ->map(fn (Certificado $certificado): array => [
                'id' => $certificado->id,
                'nome' => e($certificado->nome ?: '—'),
                'participante' => e($certificado->participante?->nome ?: '—'),
                'atividade' => e($certificado->atividade?->nome ?: '—'),
                'tipo' => e($certificado->tipo ?: '—'),
                'cargaHoraria' => $certificado->cargaHoraria ?? '—',
                'ativo' => $certificado->trashed()
                    ? '<span class="badge text-bg-danger">Excluído</span>'
                    : ($certificado->ativo
                        ? '<span class="badge text-bg-success">Ativo</span>'
                        : '<span class="badge text-bg-secondary">Inativo</span>'),
                'criado_em' => $certificado->criado_em?->format('d/m/Y H:i') ?? '—',
                'atualizado_em' => $certificado->atualizado_em?->format('d/m/Y H:i') ?? '—',
                'acoes' => view('certificados.partials.actions', [
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

    public function participantes(Request $request): JsonResponse
    {
        $this->authorizeSelector($request);
        $search = trim((string) $request->input('q', ''));
        $page = max((int) $request->input('page', 1), 1);
        $items = Participante::query()
            ->when($search !== '', fn (Builder $query): Builder => $query
                ->where(fn (Builder $filter): Builder => $filter->where('nome', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('nome')->paginate(20, ['id', 'nome', 'email'], 'page', $page);

        return response()->json([
            'results' => collect($items->items())->map(fn (Participante $item): array => [
                'id' => $item->id,
                'text' => $item->nome.($item->email ? ' · '.$item->email : ''),
            ])->values(),
            'pagination' => ['more' => $items->hasMorePages()],
        ]);
    }

    public function atividades(Request $request): JsonResponse
    {
        $this->authorizeSelector($request);
        $search = trim((string) $request->input('q', ''));
        $page = max((int) $request->input('page', 1), 1);
        $items = Atividade::query()
            ->when($search !== '', fn (Builder $query): Builder => $query->where('nome', 'like', "%{$search}%"))
            ->orderBy('nome')->paginate(20, ['id', 'nome'], 'page', $page);

        return response()->json([
            'results' => collect($items->items())->map(fn (Atividade $item): array => ['id' => $item->id, 'text' => $item->nome ?: "Atividade #{$item->id}"])->values(),
            'pagination' => ['more' => $items->hasMorePages()],
        ]);
    }

    public function create(): View
    {
        return view('certificados.form', ['certificado' => new Certificado()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $certificado = Certificado::query()->create($this->validated($request));

        return redirect()->route('certificados.show', $certificado)->with('status', 'Certificado cadastrado com sucesso.');
    }

    public function show(Certificado $certificado): View
    {
        $certificado->load(['participante', 'atividade']);

        return view('certificados.show', compact('certificado'));
    }

    public function legacy(string $arquivo): Response
    {
        abort_unless(preg_match('/^[a-z0-9-]{1,50}$/i', $arquivo) === 1, 404);
        $model = Certificado::query()->with(['participante', 'atividade.evento'])
            ->where('arquivo', $arquivo)->where('ativo', true)->whereNull('apagado_em')
            ->whereHas('atividade', fn (Builder $query): Builder => $query->where('ativo', true)->whereNull('apagado_em'))
            ->latest('id')->firstOrFail();
        $templateCode = $model->atividade?->getAttribute('template_php') ?: $model->atividade?->getAttribute('template');
        abort_unless(filled($templateCode), 404, 'O certificado não possui template legado.');

        if (! class_exists('PDF_HTML', false)) class_alias(LegacyPdfHtml::class, 'PDF_HTML');
        $certificado = (object) array_merge($model->getAttributes(), [
            'evento' => $model->atividade?->evento?->nome,
            'atividade' => $model->atividade?->nome,
            'periodo_atividade' => $model->atividade?->periodos,
            'template' => $templateCode,
            'imagemFundo' => basename((string) $model->atividade?->imagemFundo),
            'periodos' => $model->atividade?->evento?->periodos,
            'categoria' => $model->tipo,
            'participante' => $model->participante?->nome ?: $model->nome,
        ]);
        $localBackground = public_path('certificado/imagem_fundo/'.basename((string) $certificado->imagemFundo));
        $fundos = is_file($localBackground)
            ? public_path('certificado/imagem_fundo').DIRECTORY_SEPARATOR
            : 'https://certificados.nossafco.com.br/uploads/certificados/';
        $template = preg_replace('/\$this->load->library\([^;]+;/', '', (string) $certificado->template);
        $template = preg_replace("/FCPATH\s*\.\s*['\"]\\/uploads\\/certificados\\/['\"]\s*\./", '\$fundos.', (string) $template);
        // Os templates do CodeIgniter enviavam cabeçalhos e o PDF diretamente.
        // Aqui o Laravel deve ser o único responsável pela resposta HTTP.
        $template = preg_replace('/\bheader\s*\([^;]*\)\s*;/i', '', (string) $template);
        $template = preg_replace('/\$pdf->Output\s*\([^;]*\)\s*;/i', "echo \$pdf->Output('S');", (string) $template);

        ob_start();
        try { eval((string) $template); $pdf = (string) ob_get_clean(); }
        catch (\Throwable $exception) { ob_end_clean(); report($exception); abort(500, 'Não foi possível gerar o certificado legado.'); }
        abort_if($pdf === '', 500, 'O template legado não gerou o certificado.');
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="certificado.pdf"',
            'Content-Length' => (string) strlen($pdf),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function edit(Certificado $certificado): View
    {
        $certificado->load(['participante', 'atividade']);

        return view('certificados.form', compact('certificado'));
    }

    public function update(Request $request, Certificado $certificado): RedirectResponse
    {
        $certificado->update($this->validated($request));

        return redirect()->route('certificados.show', $certificado)->with('status', 'Certificado atualizado com sucesso.');
    }

    public function destroy(Certificado $certificado): RedirectResponse
    {
        $certificado->delete();

        return redirect()->route('certificados.index')->with('status', 'Certificado excluído com sucesso.');
    }

    public function toggleStatus(Certificado $certificado): RedirectResponse
    {
        $certificado->update(['ativo' => ! $certificado->ativo]);

        return redirect()->route('certificados.index')->with('status', 'Status do certificado atualizado com sucesso.');
    }

    public function restore(int $certificado): RedirectResponse
    {
        Certificado::withTrashed()->findOrFail($certificado)->restore();

        return redirect()->route('certificados.index')->with('status', 'Certificado restaurado com sucesso.');
    }

    public function forceDestroy(int $certificado): RedirectResponse
    {
        Certificado::withTrashed()->findOrFail($certificado)->forceDelete();

        return redirect()->route('certificados.index')->with('status', 'Certificado excluído definitivamente.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'participanteId' => ['nullable', 'integer', Rule::exists('participantes', 'id')->whereNull('excluido_em')],
            'atividadeId' => ['nullable', 'integer', Rule::exists('atividades', 'id')->whereNull('apagado_em')],
            'nome' => ['nullable', 'string', 'max:600'],
            'arquivo' => ['nullable', 'string', 'max:50'],
            'titulo' => ['nullable', 'string', 'max:256'],
            'titulo2' => ['nullable', 'string', 'max:256'],
            'titulo3' => ['nullable', 'string', 'max:256'],
            'titulo4' => ['nullable', 'string', 'max:100'],
            'cargaHoraria' => ['nullable', 'integer', 'min:0'],
            'outrosParticipantes' => ['nullable', 'string', 'max:700'],
            'tipo' => ['nullable', 'string', 'max:50'],
            'ativo' => ['required', 'boolean'],
            'arquivo_old' => ['nullable', 'string', 'max:50'],
        ]);
    }

    private function authorizeSelector(Request $request): void
    {
        $permissions = (array) $request->session()->get('gi_context.permissoes', []);
        abort_unless(
            in_array('certificados.listar', $permissions, true)
            || in_array('certificados.criar', $permissions, true)
            || in_array('certificados.editar', $permissions, true),
            403
        );
    }
}
