<?php

namespace App\Http\Controllers;

use App\Models\CertificadoA1;
use App\Models\Template;
use App\Models\Variavel;
use App\Models\FonteLayout;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

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
    public function builder(Template $template): View
    {
        $variables = Variavel::query()->where('ativo', true)->orderBy('tipo')->orderBy('id')->get()
            ->map(fn (Variavel $variable): array => [
                'id' => $variable->id, 'type' => $variable->tipo,
                'label' => $variable->tipo === 'texto' ? Str::limit($variable->texto ?: 'Texto sem conteúdo', 45) : ($variable->imagem ?: 'Imagem'),
                'text' => $variable->texto, 'image' => $variable->imageUrl(),
                'width' => max((int) ($variable->largura ?: ($variable->tipo === 'imagem' ? 40 : 70)), 1),
                'height' => max((int) ($variable->altura ?: ($variable->tipo === 'imagem' ? 30 : 12)), 1),
                'x' => max((int) ($variable->pos_x ?: 0), 0), 'y' => max((int) ($variable->pox_y ?: 0), 0),
                'color' => $variable->cor ?: '#111827', 'align' => $variable->alinhamento ?: 'esquerda',
            ])->values();

        $fonts = FonteLayout::query()->orderBy('nome')->get()->map(fn (FonteLayout $font): array => ['name' => $font->nome, 'url' => $font->url()])->values();
        return view('templates.builder', compact('template', 'variables', 'fonts'));
    }

    public function saveBuilder(Request $request, Template $template): RedirectResponse
    {
        $request->validate(['layout_json' => ['required', 'json']]);
        $request->merge(['elementos' => json_decode((string) $request->input('layout_json'), true)]);
        $validated = $request->validate([
            'elementos' => ['present', 'array', 'max:200'],
            'elementos.*.uid' => ['required', 'string', 'max:80'],
            'elementos.*.variable_id' => ['required', 'integer', Rule::exists('variaveis', 'id')->whereNull('apagado_em')],
            'elementos.*.type' => ['required', Rule::in(['texto', 'imagem'])],
            'elementos.*.text' => ['nullable', 'string', 'max:5000'],
            'elementos.*.x' => ['required', 'numeric', 'min:0'], 'elementos.*.y' => ['required', 'numeric', 'min:0'],
            'elementos.*.width' => ['required', 'numeric', 'min:1'], 'elementos.*.height' => ['required', 'numeric', 'min:1'],
            'elementos.*.color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'elementos.*.align' => ['nullable', Rule::in(['esquerda', 'direita', 'centralizado', 'justificado'])],
            'elementos.*.font_family' => ['nullable', 'string', 'max:100'], 'elementos.*.font_size' => ['nullable', 'numeric', 'min:1', 'max:300'],
            'elementos.*.bold' => ['nullable', 'boolean'], 'elementos.*.italic' => ['nullable', 'boolean'], 'elementos.*.underline' => ['nullable', 'boolean'],
        ]);
        $template->update(['elementos_layout' => array_values($validated['elementos'])]);
        return redirect()->route('templates.builder', $template)->with('status', 'Layout salvo com sucesso.');
    }

    public function previewPdf(Request $request, Template $template): Response
    {
        File::ensureDirectoryExists(storage_path('fonts'), 0775, true);
        $request->validate(['layout_json' => ['required', 'json']]);
        $elements = collect(json_decode((string) $request->input('layout_json'), true) ?: [])->take(200);
        $variables = Variavel::query()->whereIn('id', $elements->pluck('variable_id')->filter()->unique())->get()->keyBy('id');
        $elements = $elements->map(function (array $element) use ($variables): ?array {
            $variable = $variables->get((int) ($element['variable_id'] ?? 0));
            if (! $variable || ! in_array($variable->tipo, ['texto', 'imagem'], true)) return null;
            return [
                'type' => $variable->tipo,
                'text' => Str::limit((string) ($element['text'] ?? $variable->texto ?? ''), 5000, ''),
                'image' => $variable->tipo === 'imagem' ? $this->fileDataUri($variable->imageExists() ? public_path('certificado/imagem_fundo/'.$variable->imagem) : null) : null,
                'x' => max((float) ($element['x'] ?? 0), 0), 'y' => max((float) ($element['y'] ?? 0), 0),
                'width' => max((float) ($element['width'] ?? 1), 1), 'height' => max((float) ($element['height'] ?? 1), 1),
                'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($element['color'] ?? '')) ? $element['color'] : '#111827',
                'align' => in_array(($element['align'] ?? ''), ['esquerda', 'direita', 'centralizado', 'justificado'], true) ? $element['align'] : 'esquerda',
                'font_family' => Str::limit(preg_replace('/[^\pL\pN _-]/u', '', (string) ($element['font_family'] ?? 'Arial')) ?: 'Arial', 100, ''),
                'font_size' => min(max((float) ($element['font_size'] ?? 12), 1), 300),
                'bold' => filter_var($element['bold'] ?? false, FILTER_VALIDATE_BOOLEAN), 'italic' => filter_var($element['italic'] ?? false, FILTER_VALIDATE_BOOLEAN), 'underline' => filter_var($element['underline'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        })->filter()->values();
        $width = max((int) $template->largura, 1); $height = max((int) $template->altura, 1);
        $background = $this->fileDataUri($template->backgroundExists() ? public_path('certificado/imagem_fundo/'.$template->fundo) : null);
        $fonts = FonteLayout::query()->get()->map(fn (FonteLayout $font): array => ['name' => $font->nome, 'data' => $this->fileDataUri($font->path()), 'format' => strtolower(pathinfo($font->arquivo, PATHINFO_EXTENSION))])->filter(fn (array $font): bool => filled($font['data']));
        $paper = [0, 0, $width * 2.834645669, $height * 2.834645669];

        return Pdf::loadView('templates.preview-pdf', compact('template', 'elements', 'width', 'height', 'background', 'fonts'))
            ->setPaper($paper)->stream('preview-template-'.$template->id.'.pdf', ['Attachment' => false]);
    }

    public function uploadFont(Request $request, Template $template): JsonResponse
    {
        $validated = $request->validate(['fonte' => ['required', 'file', 'mimes:ttf,otf,woff,woff2', 'max:10240']]);
        $file = $validated['fonte']; $extension = strtolower($file->getClientOriginalExtension());
        $original = $file->getClientOriginalName(); $name = Str::limit(preg_replace('/[^\pL\pN _-]/u', '', pathinfo($original, PATHINFO_FILENAME)) ?: 'Fonte personalizada', 100, '');
        $filename = hash('sha1', Str::uuid()->toString()).'.'.$extension; $directory = public_path('certificado/fontes');
        File::ensureDirectoryExists($directory); $file->move($directory, $filename);
        $font = FonteLayout::query()->create(['nome' => $name ?: 'Fonte personalizada', 'arquivo' => $filename, 'nome_original' => $original]);
        return response()->json(['font' => ['name' => $font->nome, 'url' => $font->url()]], 201);
    }
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
    private function fileDataUri(?string $path): ?string
    {
        if (! $path || ! is_file($path)) return null;
        $mime = File::mimeType($path) ?: 'image/png';
        return 'data:'.$mime.';base64,'.base64_encode((string) File::get($path));
    }
}
