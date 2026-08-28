<?php

namespace App\Http\Controllers;

use App\Models\CertificadoA1;
use App\Models\Template;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TemplateController extends Controller
{
    private const COLUMNS = ['id', 'nome', 'certificado_a1', 'pagina', 'layout_pagina', 'ativo', 'crido_em', 'alterado_em'];

    public function index(Request $request): View
    {
        return view('templates.index', ['permissions' => (array) $request->session()->get('gi_context.permissoes', [])]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = Template::withTrashed()->with('certificadoA1');
        $total = (clone $query)->count();
        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(fn (Builder $query): Builder => $query->where('nome', 'like', "%{$search}%")
                ->orWhere('pagina', 'like', "%{$search}%")->orWhere('layout_pagina', 'like', "%{$search}%")
                ->orWhereHas('certificadoA1', fn (Builder $item): Builder => $item->where('nome', 'like', "%{$search}%")));
        }
        $filtered = (clone $query)->count();
        $column = self::COLUMNS[(int) $request->input('order.0.column', 0)] ?? 'id';
        $permissions = (array) $request->session()->get('gi_context.permissoes', []);
        $data = $query->orderBy($column, $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc')
            ->skip(max((int) $request->input('start', 0), 0))->take(min(max((int) $request->input('length', 10), 1), 100))->get()
            ->map(fn (Template $template): array => [
                'id' => $template->id, 'nome' => e($template->nome ?: '—'),
                'certificado' => e($template->certificadoA1 ? "#{$template->certificadoA1->id} · {$template->certificadoA1->nome}" : '—'),
                'pagina' => e($template->pagina ?: '—'), 'layout_pagina' => e($template->layout_pagina ?: '—'),
                'ativo' => $template->trashed() ? '<span class="badge text-bg-danger">Excluído</span>' : ($template->ativo ? '<span class="badge text-bg-success">Ativo</span>' : '<span class="badge text-bg-secondary">Inativo</span>'),
                'crido_em' => $template->crido_em?->format('d/m/Y H:i') ?? '—', 'alterado_em' => $template->alterado_em?->format('d/m/Y H:i') ?? '—',
                'acoes' => view('templates.partials.actions', ['template' => $template, 'permissions' => $permissions])->render(),
            ]);
        return response()->json(['draw' => (int) $request->input('draw'), 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $data]);
    }

    public function certificadosA1(Request $request): JsonResponse
    {
        $permissions = (array) $request->session()->get('gi_context.permissoes', []);
        abort_unless(in_array('template.criar', $permissions, true) || in_array('template.editar', $permissions, true), 403);
        $search = trim((string) $request->input('q', ''));
        $items = CertificadoA1::query()->when($search !== '', fn (Builder $query): Builder => $query->where(fn (Builder $filter): Builder => $filter->where('nome', 'like', "%{$search}%")->orWhere('id', 'like', "%{$search}%")))
            ->orderBy('nome')->paginate(20, ['id', 'nome'], 'page', max((int) $request->input('page', 1), 1));
        return response()->json(['results' => collect($items->items())->map(fn (CertificadoA1 $item): array => ['id' => $item->id, 'text' => "#{$item->id} · ".($item->nome ?: 'Sem nome')])->values(), 'pagination' => ['more' => $items->hasMorePages()]]);
    }

    public function create(): View { return view('templates.form', ['template' => new Template()]); }
    public function store(Request $request): RedirectResponse
    {
        $data = $this->normalizePage($this->validated($request)); unset($data['remover_fundo']);
        if ($request->hasFile('fundo')) $data['fundo'] = $this->storeBackground($request);
        $template = Template::query()->create($data);
        return redirect()->route('templates.show', $template)->with('status', 'Template cadastrado com sucesso.');
    }
    public function show(Template $template): View { $template->load('certificadoA1'); return view('templates.show', compact('template')); }
    public function edit(Template $template): View { $template->load('certificadoA1'); return view('templates.form', compact('template')); }
    public function update(Request $request, Template $template): RedirectResponse
    {
        $data = $this->normalizePage($this->validated($request)); $old = $template->fundo;
        if ($request->hasFile('fundo')) { $data['fundo'] = $this->storeBackground($request); $this->removeBackground($old); }
        elseif ($request->boolean('remover_fundo')) { $this->removeBackground($old); $data['fundo'] = null; }
        unset($data['remover_fundo']); $template->update($data);
        return redirect()->route('templates.show', $template)->with('status', 'Template atualizado com sucesso.');
    }
    public function toggleStatus(Template $template): RedirectResponse { $template->update(['ativo' => ! $template->ativo]); return redirect()->route('templates.index')->with('status', 'Status atualizado com sucesso.'); }
    public function destroy(Template $template): RedirectResponse { $template->delete(); return redirect()->route('templates.index')->with('status', 'Template excluído com sucesso.'); }
    public function forceDestroy(int $template): RedirectResponse { $model = Template::withTrashed()->findOrFail($template); $this->removeBackground($model->fundo); $model->forceDelete(); return redirect()->route('templates.index')->with('status', 'Template excluído definitivamente.'); }

    private function validated(Request $request): array
    {
        return $request->validate(['nome' => ['nullable','string','max:100'], 'fundo' => ['nullable','image','mimes:png,jpg,jpeg','max:10240'], 'remover_fundo' => ['nullable','boolean'], 'ativo' => ['required','boolean'], 'certificado_a1' => ['nullable','integer',Rule::exists('certificados_a1','id')->whereNull('apagado_em')], 'largura' => ['nullable','integer','min:0'], 'altura' => ['nullable','integer','min:0'], 'pagina' => ['nullable',Rule::in(['A4','Carta','Oficio','Personalizado'])], 'layout_pagina' => ['nullable',Rule::in(['Retrato','Paisagem'])]]);
    }
    private function normalizePage(array $data): array
    {
        $data['pagina'] ??= 'A4';
        $data['layout_pagina'] ??= 'Retrato';
        $dimensions = [
            'A4' => [210, 297],
            'Carta' => [216, 279],
            'Oficio' => [216, 356],
        ];

        if (isset($dimensions[$data['pagina']])) {
            [$width, $height] = $dimensions[$data['pagina']];
            $data['largura'] = $data['layout_pagina'] === 'Paisagem' ? $height : $width;
            $data['altura'] = $data['layout_pagina'] === 'Paisagem' ? $width : $height;
        } else {
            $data['largura'] ??= $data['layout_pagina'] === 'Paisagem' ? 297 : 210;
            $data['altura'] ??= $data['layout_pagina'] === 'Paisagem' ? 210 : 297;
        }

        return $data;
    }
    private function storeBackground(Request $request): string { $file=$request->file('fundo'); $name=hash('sha1',Str::uuid()->toString()).'.'.strtolower($file->getClientOriginalExtension()); $dir=public_path('certificado/imagem_fundo'); File::ensureDirectoryExists($dir); $file->move($dir,$name); return $name; }
    private function removeBackground(?string $name): void { if (filled($name) && basename($name)===$name) File::delete(public_path('certificado/imagem_fundo/'.$name)); }
}
