<?php

namespace App\Http\Controllers;

use App\Models\ListaParticipante;
use App\Models\Responsavel;
use App\Services\CertificadoPdfGenerator;
use App\Services\CertificadoImageGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CertificadoNovoController extends Controller
{
    private const COLUMNS = ['id', 'id', 'id', 'id', 'participante_id', 'id', 'id', 'id', 'id', 'id', 'id', 'ativo', 'id'];

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
        $responsibleIds = $items->pluck('novoCertificado.template.responsaveis')->filter()->flatMap(fn (string $ids): array => array_filter(array_map('intval', explode(',', $ids))))->unique();
        $responsibleNames = Responsavel::withTrashed()->with('participante')->whereIn('id', $responsibleIds)->get()->mapWithKeys(fn (Responsavel $responsavel): array => [$responsavel->id => $responsavel->participante?->nome])->filter();

        return response()->json([
            'draw' => (int) $request->input('draw'), 'recordsTotal' => $total, 'recordsFiltered' => $filtered,
            'data' => $items->map(fn (ListaParticipante $item): array => [
                'id' => $item->id,
                'emissao' => e($item->novoCertificado?->nome ?: '#'.$item->novo_certificado_id),
                'template' => e($item->novoCertificado?->template?->nome ?: '—'),
                'responsaveis' => e(collect(explode(',', (string) $item->novoCertificado?->template?->responsaveis))->filter()->map(fn ($id) => $responsibleNames->get((int) $id))->filter()->implode(', ') ?: '—'),
                'participante' => e($item->participante?->nome ?: '—'),
                'evento' => e($item->novoCertificado?->evento?->nome ?: $item->novoCertificado?->atividade?->evento?->nome ?: '—'),
                'atividade' => e($item->novoCertificado?->atividade?->nome ?: '—'),
                'data_certificado' => $item->novoCertificado?->data_emissao?->format('d/m/Y') ?? '—',
                'horas' => e((string) (data_get($item->snapshot_dados, 'atividade.carga_horaria') ?: $item->novoCertificado?->carga_horaria ?: '—')),
                'pdf' => $item->arquivoExists() && filled($item->codigo) ? '<a target="_blank" rel="noopener noreferrer" href="'.e(route('certificadosnovos.public.pdf', $item->codigo)).'" class="btn btn-sm btn-outline-danger" title="Abrir PDF"><i class="bi bi-file-earmark-pdf"></i></a>' : (in_array('certificadosnovos.gerar_pdf',$permissions,true)?'<button type="button" class="btn btn-sm btn-link js-generate-certificate p-0" data-url="'.e(route('certificadosnovos.generate',$item)).'">Gerar</button>':'—'),
                'img' => $item->imagemExists() && filled($item->codigo_img) ? '<a target="_blank" rel="noopener noreferrer" href="'.e(route('certificadosnovos.public.image', $item->codigo_img)).'" class="btn btn-sm btn-outline-primary" title="Abrir imagem"><i class="bi bi-image"></i></a>' : (in_array('certificadosnovos.gerar_pdf',$permissions,true)?'<button type="button" class="btn btn-sm btn-link js-generate-certificate p-0" data-url="'.e(route('certificadosnovos.generate-image',$item)).'">Gerar</button>':'—'),
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

    public function generate(Request $request, ListaParticipante $item, CertificadoPdfGenerator $generator): RedirectResponse|JsonResponse
    {
        try { $generator->generate($item); } catch (\Throwable) {
            if ($request->expectsJson()) return response()->json(['message'=>'Não foi possível gerar o PDF.'],422);
            return back()->withErrors(['pdf' => 'Não foi possível gerar o PDF. Consulte o registro de erros.']);
        }
        if ($request->expectsJson()) return response()->json(['html'=>'<a target="_blank" rel="noopener noreferrer" href="'.e(route('certificadosnovos.public.pdf',$item->fresh()->codigo)).'" class="btn btn-sm btn-outline-danger" title="Abrir PDF"><i class="bi bi-file-earmark-pdf"></i></a>']);
        return back()->with('status', 'PDF gerado com sucesso.');
    }

    public function generateImage(Request $request, ListaParticipante $item, CertificadoImageGenerator $generator): RedirectResponse|JsonResponse
    {
        try { $generator->generate($item); } catch (\Throwable $exception) {
            report($exception);
            if ($request->expectsJson()) return response()->json(['message'=>'Não foi possível gerar a imagem.'],422);
            return back()->withErrors(['img'=>'Não foi possível gerar a imagem.']);
        }
        $item->refresh();
        $html='<a target="_blank" rel="noopener noreferrer" href="'.e(route('certificadosnovos.public.image',$item->codigo_img)).'" class="btn btn-sm btn-outline-primary" title="Abrir imagem"><i class="bi bi-image"></i></a>';
        if ($request->expectsJson()) return response()->json(compact('html'));
        return back()->with('status','Imagem gerada com sucesso.');
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

    public function publicImage(string $codigo): View
    {
        $item=$this->publicImageItem($codigo);
        $dimensions=@getimagesize($item->imagemPath());
        $landscape=($dimensions[0]??1)>=($dimensions[1]??1);
        return view('certificadosnovos.certificados.public-image',compact('item','landscape'));
    }

    public function publicImageFile(string $codigo): BinaryFileResponse
    {
        $item=$this->publicImageItem($codigo);
        return response()->file($item->imagemPath(),['Content-Type'=>'image/png','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }

    public function downloadPublicImage(string $codigo): BinaryFileResponse
    {
        $item=$this->publicImageItem($codigo);
        return response()->download($item->imagemPath(),'certificado-'.$codigo.'.png',['Content-Type'=>'image/png','X-Content-Type-Options'=>'nosniff']);
    }

    public function downloadPublicPdfByImageCode(string $codigo): BinaryFileResponse
    {
        $item=$this->publicImageItem($codigo);
        abort_unless($item->arquivoExists(),404);
        return response()->download($item->arquivoPath(),'certificado-'.$codigo.'.pdf',['Content-Type'=>'application/pdf','X-Content-Type-Options'=>'nosniff']);
    }

    private function publicImageItem(string $codigo): ListaParticipante
    {
        abort_unless(preg_match('/^[A-Za-z0-9-]{6,64}$/',$codigo)===1,404);
        $item=ListaParticipante::query()->with(['participante','novoCertificado.evento','novoCertificado.atividade.evento'])->where('codigo_img',$codigo)->where('ativo',true)->whereHas('novoCertificado',fn(Builder $query):Builder=>$query->where('ativo',true)->whereNull('apagado_em'))->firstOrFail();
        abort_unless($item->imagemExists(),404);
        return $item;
    }
}
