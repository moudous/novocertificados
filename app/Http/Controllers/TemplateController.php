<?php

namespace App\Http\Controllers;

use App\Models\CertificadoA1;
use App\Models\Template;
use App\Models\FonteLayout;
use App\Models\Atividade;
use App\Models\BibliotecaImagem;
use App\Models\ParticipanteTeste;
use App\Models\Responsavel;
use App\Models\RubricaParticipante;
use App\Services\TemplateLayoutRenderer;
use App\Services\PdfDigitalSigner;
use App\Services\CertificadoImageGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
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
        $data = $this->normalizePage($this->validated($request));
        if ($request->hasFile('fundo')) $data['fundo'] = $this->storeBackground($request);
        $data['fundo_colorido_ativo'] = ($data['tipo_fundo'] ?? 'imagem') === 'colorido';
        if (($data['tipo_fundo'] ?? 'imagem') === 'colorido') {
            if (blank($data['cor_fundo'] ?? null)) return back()->withErrors(['cor_fundo' => 'Selecione a cor do fundo.'])->withInput();
            $data['fundo_colorido'] = $this->createColoredBackground($data['cor_fundo'], $data['largura'], $data['altura']);
        }
        if (($data['tipo_fundo'] ?? 'imagem') === 'degrade') {
            if (blank($data['cor_degrade_inicio'] ?? null) || blank($data['cor_degrade_fim'] ?? null)) return back()->withErrors(['cor_degrade_inicio' => 'Selecione as duas cores do degradê.'])->withInput();
            $data['fundo_degrade'] = $this->createGradientBackground($data['cor_degrade_inicio'], $data['cor_degrade_fim'], $data['direcao_degrade'] ?? 'cima_baixo', $data['largura'], $data['altura']);
        }
        unset($data['remover_fundo'], $data['remover_fundo_colorido'], $data['remover_fundo_degrade']);
        $template = Template::query()->create($data);
        return redirect()->route('templates.show', $template)->with('status', 'Template cadastrado com sucesso.');
    }
    public function show(Template $template): View { $template->load('certificadoA1'); return view('templates.show', compact('template')); }
    public function edit(Template $template): View { $template->load(['certificadoA1','imagemBiblioteca']); return view('templates.form', compact('template')); }
    public function duplicate(Template $template): RedirectResponse
    {
        $createdFiles = [];
        try {
            $copy = DB::transaction(function () use ($template, &$createdFiles): Template {
                $copy = $template->replicate();
                $baseName = filled($template->nome) ? (string) $template->nome : 'Template';
                $copy->nome = Str::limit($baseName, 90, '').' # cópia';
                foreach (['fundo', 'fundo_colorido', 'fundo_degrade'] as $attribute) {
                    $copy->{$attribute} = $this->duplicateBackground($template->{$attribute}, $createdFiles);
                }
                $copy->save();
                $imageIds=[];
                foreach($template->imagensTemplate()->get() as $image){$imageCopy=$image->replicate();$imageCopy->template_id=$copy->id;$imageCopy->save();$imageIds[$image->id]=$imageCopy->id;}
                if($imageIds){$layout=array_map(function(array $element)use($imageIds):array{if(isset($imageIds[(int)($element['template_image_id']??0)]))$element['template_image_id']=$imageIds[(int)$element['template_image_id']];return $element;},$copy->elementos_layout??[]);$copy->update(['elementos_layout'=>$layout]);}
                return $copy;
            });
        } catch (\Throwable $exception) {
            foreach ($createdFiles as $file) File::delete($file);
            report($exception);
            return back()->withErrors(['template' => 'Não foi possível duplicar o template e seus arquivos de fundo.']);
        }

        return redirect()->route('templates.edit', $copy)->with('status', 'Template duplicado. Revise o nome e salve as alterações.');
    }
    public function builder(Request $request, Template $template, TemplateLayoutRenderer $renderer): View
    {
        $uploadedFonts = FonteLayout::query()->orderBy('nome')->get()->map(fn (FonteLayout $font): array => ['name' => $font->nome, 'url' => $font->url()]);
        $fallbackFonts = collect(TemplateLayoutRenderer::FALLBACK_FONTS)->map(fn (string $file, string $name): array => [
            'name' => $name,
            'url' => route('templates.fallback-font', ['family' => $name]),
        ]);
        $fonts = $uploadedFonts->concat($fallbackFonts)->unique('name')->values();
        $dynamicSources = TemplateLayoutRenderer::SOURCES;
        $libraryImages = BibliotecaImagem::query()->where('ativo', true)->orderBy('categoria')->orderBy('nome')->get();
        $templateImages = $template->imagensTemplate()->orderBy('nome')->get();
        $testParticipants = ParticipanteTeste::query()->with('participante')->orderBy('id')->get();
        $activities = Atividade::query()->where('ativo', true)->orderBy('nome')->get();
        $testSelection = (array) $request->session()->get('armazem.templates.construtor.participante', []);
        $responsibleSignatures = RubricaParticipante::query()->with('participante')->where('ativo', true)
            ->whereHas('participante.responsavel', fn (Builder $query): Builder => $query->where('ativo', true))->orderBy('participante_id')->get();
        $builderConfig = [
            'width'=>max((int)$template->largura,1),'height'=>max((int)$template->altura,1),'elements'=>$template->elementos_layout??[],
            'fonts'=>$fonts->values()->all(),'sources'=>$dynamicSources,
            'librarySvgs'=>$libraryImages->filter(fn(BibliotecaImagem $image):bool=>filled($image->svg))->mapWithKeys(fn(BibliotecaImagem $image):array=>[$image->id=>$image->svg])->all(),
            'templateImages'=>$templateImages->map(fn($image):array=>['id'=>$image->id,'name'=>$image->nome,'svg'=>$image->svg,'url'=>$image->dataUrl(),'library_image_id'=>$image->biblioteca_imagem_id,'element_uid'=>$image->element_uid])->values()->all(),
            'previewUrl'=>route('templates.builder.preview',$template),'previewImageUrl'=>route('templates.builder.preview-image',$template),'fontUploadUrl'=>route('templates.builder.fonts.store',$template),
            'imageStoreUrl'=>route('templates.builder.images.store',$template),'imageDeleteUrl'=>route('templates.builder.images.destroy',[$template,'__IMAGE__']),
            'validationPreviewUrl'=>route('certificadosnovos.public.pdf','TESTE-000001'),
            'qrPreviews'=>collect(TemplateLayoutRenderer::QR_STYLES)->mapWithKeys(fn(string $label,string $style):array=>[$style=>$renderer->validationQrDataUri(route('certificadosnovos.public.pdf','TESTE-000001'),$style)])->all(),
        ];
        return view('templates.builder', compact('template', 'fonts', 'dynamicSources', 'libraryImages', 'templateImages', 'testParticipants', 'activities', 'testSelection', 'responsibleSignatures', 'builderConfig'));
    }

    public function fallbackFont(string $family): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $file = TemplateLayoutRenderer::FALLBACK_FONTS[$family] ?? null;
        abort_unless($file, 404);
        $path = base_path('vendor/dompdf/dompdf/lib/fonts/'.$file);
        abort_unless(is_file($path), 404);

        return response()->file($path, ['Content-Type' => 'font/ttf', 'Cache-Control' => 'public, max-age=31536000, immutable']);
    }

    public function saveBuilder(Request $request, Template $template): RedirectResponse
    {
        $request->validate(['layout_json' => ['required', 'json']]);
        $request->merge(['elementos' => json_decode((string) $request->input('layout_json'), true)]);
        $validated = $request->validate([
            'elementos' => ['present', 'array', 'max:200'],
            'elementos.*.uid' => ['required', 'string', 'max:80'],
            'elementos.*.type' => ['required', Rule::in(['text', 'rich_text', 'image'])],
            'elementos.*.source_type' => ['required', Rule::in(['fixed', 'dynamic', 'validation_link', 'validation_qr', 'library', 'responsible_signature', 'template_image'])],
            'elementos.*.qr_style' => ['nullable', Rule::in(array_keys(TemplateLayoutRenderer::QR_STYLES))],
            'elementos.*.source_key' => ['nullable', Rule::in(array_keys(TemplateLayoutRenderer::SOURCES))],
            'elementos.*.library_image_id' => ['nullable', 'integer', Rule::exists('biblioteca_imagens', 'id')->whereNull('apagado_em')],
            'elementos.*.template_image_id' => ['nullable', 'integer', Rule::exists('imagens_template', 'id')->where(fn($query)=>$query->where('template_id',$template->id))],
            'elementos.*.rubrica_id' => ['nullable', 'integer', Rule::exists('rubricas_participantes', 'id')->where(fn ($query) => $query->where('ativo', true)->whereNull('apagado_em'))],
            'elementos.*.content' => ['nullable', 'string', 'max:10000'],
            'elementos.*.rotation' => ['nullable', 'integer', Rule::in([0, 90, 180, 270])],
            'elementos.*.x' => ['required', 'numeric', 'min:0'], 'elementos.*.y' => ['required', 'numeric', 'min:0'],
            'elementos.*.width' => ['required', 'numeric', 'min:1'], 'elementos.*.height' => ['required', 'numeric', 'min:1'],
            'elementos.*.color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'elementos.*.align' => ['nullable', Rule::in(['esquerda', 'direita', 'centralizado', 'justificado'])],
            'elementos.*.font_family' => ['nullable', 'string', 'max:100'], 'elementos.*.font_size' => ['nullable', 'numeric', 'min:1', 'max:300'],
            'elementos.*.bold' => ['nullable', 'boolean'], 'elementos.*.italic' => ['nullable', 'boolean'], 'elementos.*.underline' => ['nullable', 'boolean'],
        ]);
        $elements = array_map(function (array $element): array {
            foreach (['x', 'y', 'width', 'height'] as $property) {
                $element[$property] = round((float) $element[$property], 1);
            }
            return $element;
        }, array_values($validated['elementos']));
        $template->update(['elementos_layout' => $elements]);
        return redirect()->route('templates.builder', $template)->with('status', 'Layout salvo com sucesso.');
    }

    public function previewPdf(Request $request, Template $template, TemplateLayoutRenderer $renderer,PdfDigitalSigner $signer): Response
    {
        File::ensureDirectoryExists(storage_path('fonts'), 0775, true);
        $request->validate([
            'layout_json' => ['required', 'json'],
            'participante_teste_id' => ['nullable', 'required_with:atividade_id', 'integer', Rule::exists('participantes_de_teste', 'id')->whereNull('apagado_em')],
            'atividade_id' => ['nullable', 'required_with:participante_teste_id', 'integer', Rule::exists('atividades', 'id')->where(fn ($query) => $query->where('ativo', true)->whereNull('apagado_em'))],
        ]);
        if ($request->has('participante_teste_id') || $request->has('atividade_id')) {
            $request->session()->put('armazem.templates.construtor.participante', [
                'participante_teste_id' => $request->integer('participante_teste_id') ?: null,
                'atividade_id' => $request->integer('atividade_id') ?: null,
            ]);
        }
        $test = $request->filled('participante_teste_id') ? ParticipanteTeste::with('participante')->findOrFail($request->integer('participante_teste_id')) : null;
        $activity = $request->filled('atividade_id') ? Atividade::with('evento')->findOrFail($request->integer('atividade_id')) : null;
        $context = $this->previewContext($test?->participante, $activity, null, null);
        $elements = collect($renderer->elements(json_decode((string)$request->input('layout_json'), true) ?: [], $context));
        $width = max((int) $template->largura, 1); $height = max((int) $template->altura, 1);
        $background = $renderer->background($template);
        $fonts = collect($renderer->fonts());
        $paper = [0, 0, $width * 2.834645669, $height * 2.834645669];

        $pdf=Pdf::loadView('templates.preview-pdf', compact('template', 'elements', 'width', 'height', 'background', 'fonts'))->setPaper($paper);
        $output=$signer->output($pdf,$template->certificadoA1,'Preview do template #'.$template->id);
        return response($output,200,['Content-Type'=>'application/pdf','Content-Disposition'=>'inline; filename="preview-template-'.$template->id.'.pdf"']);
    }

    public function previewImage(Request $request, Template $template, CertificadoImageGenerator $generator): View
    {
        $request->validate([
            'layout_json'=>['required','json'],
            'participante_teste_id'=>['nullable','integer',Rule::exists('participantes_de_teste','id')->whereNull('apagado_em')],
            'atividade_id'=>['nullable','integer',Rule::exists('atividades','id')->where(fn($query)=>$query->where('ativo',true)->whereNull('apagado_em'))],
        ]);
        $test=$request->filled('participante_teste_id')?ParticipanteTeste::with('participante')->findOrFail($request->integer('participante_teste_id')):null;
        $activity=$request->filled('atividade_id')?Atividade::with('evento')->findOrFail($request->integer('atividade_id')):null;
        $context=$this->previewContext($test?->participante,$activity,null,null);
        $png=$generator->render($template,json_decode((string)$request->input('layout_json'),true)?:[],$context);
        $image='data:image/png;base64,'.base64_encode($png);
        return view('templates.preview-image',[
            'image'=>$image,
            'participant'=>$context['participante']['nome'],
            'event'=>$context['evento']['nome'],
            'activity'=>$context['atividade']['nome'],
            'hours'=>$context['atividade']['carga_horaria'],
        ]);
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
        $data = $this->normalizePage($this->validated($request)); $old = $template->fundo; $oldColored = $template->fundo_colorido; $oldGradient = $template->fundo_degrade;
        $type = $data['tipo_fundo'] ?? 'imagem'; $data['fundo_colorido_ativo'] = $type === 'colorido';
        if ($request->hasFile('fundo')) { $data['fundo'] = $this->storeBackground($request); $this->removeBackground($old); }
        elseif ($request->boolean('remover_fundo')) { $this->removeBackground($old); $data['fundo'] = null; }
        if ($request->boolean('remover_fundo_colorido')) { $this->removeBackground($oldColored); $data['fundo_colorido'] = null; $data['cor_fundo'] = null; }
        if ($type === 'colorido' && filled($data['cor_fundo'] ?? null)) {
            if ($template->coloredBackgroundExists() && ! $request->boolean('remover_fundo_colorido')) return back()->withErrors(['cor_fundo' => 'Remova primeiro o fundo colorido atual para gerar uma imagem com outra cor.'])->withInput();
            $data['fundo_colorido'] = $this->createColoredBackground($data['cor_fundo'], $data['largura'], $data['altura']);
        } elseif ($type === 'colorido' && ! $template->coloredBackgroundExists() && ! $request->boolean('remover_fundo_colorido')) {
            return back()->withErrors(['cor_fundo' => 'Selecione a cor do fundo.'])->withInput();
        }
        if ($request->boolean('remover_fundo_degrade')) { $this->removeBackground($oldGradient); $data['fundo_degrade'] = null; $data['cor_degrade_inicio'] = null; $data['cor_degrade_fim'] = null; }
        if ($type === 'degrade' && filled($data['cor_degrade_inicio'] ?? null) && filled($data['cor_degrade_fim'] ?? null)) {
            if ($template->gradientBackgroundExists() && ! $request->boolean('remover_fundo_degrade')) return back()->withErrors(['cor_degrade_inicio' => 'Remova primeiro o fundo degradê atual para gerar outro.'])->withInput();
            $data['fundo_degrade'] = $this->createGradientBackground($data['cor_degrade_inicio'], $data['cor_degrade_fim'], $data['direcao_degrade'] ?? 'cima_baixo', $data['largura'], $data['altura']);
        } elseif ($type === 'degrade' && ! $template->gradientBackgroundExists() && ! $request->boolean('remover_fundo_degrade')) {
            return back()->withErrors(['cor_degrade_inicio' => 'Selecione as duas cores do degradê.'])->withInput();
        }
        unset($data['remover_fundo'], $data['remover_fundo_colorido'], $data['remover_fundo_degrade']); $template->update($data);
        return redirect()->route('templates.show', $template)->with('status', 'Template atualizado com sucesso.');
    }
    public function toggleStatus(Template $template): RedirectResponse { $template->update(['ativo' => ! $template->ativo]); return redirect()->route('templates.index')->with('status', 'Status atualizado com sucesso.'); }
    public function destroy(Template $template): RedirectResponse { $template->delete(); return redirect()->route('templates.index')->with('status', 'Template excluído com sucesso.'); }
    public function forceDestroy(int $template): RedirectResponse { $model = Template::withTrashed()->findOrFail($template); $this->removeBackground($model->fundo); $this->removeBackground($model->fundo_colorido); $this->removeBackground($model->fundo_degrade); $model->forceDelete(); return redirect()->route('templates.index')->with('status', 'Template excluído definitivamente.'); }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nome' => ['nullable','string','max:100'], 'fundo' => ['nullable','image','mimes:png,jpg,jpeg','max:10240'],
            'remover_fundo' => ['nullable','boolean'], 'remover_fundo_colorido' => ['nullable','boolean'], 'remover_fundo_degrade' => ['nullable','boolean'],
            'fundo_colorido_ativo' => ['nullable','boolean'], 'tipo_fundo' => ['required',Rule::in(['imagem','biblioteca','colorido','degrade'])],
            'biblioteca_imagem_id' => ['nullable','integer',Rule::exists('biblioteca_imagens','id')->where(fn($q)=>$q->where('ativo',true)->whereNull('apagado_em'))],
            'cor_fundo' => ['nullable','regex:/^#[0-9a-fA-F]{6}$/'], 'cor_degrade_inicio' => ['nullable','regex:/^#[0-9a-fA-F]{6}$/'], 'cor_degrade_fim' => ['nullable','regex:/^#[0-9a-fA-F]{6}$/'], 'direcao_degrade' => ['nullable',Rule::in(['cima_baixo','baixo_cima','esquerda_direita','direita_esquerda','superior_esquerdo_inferior_direito','inferior_direito_superior_esquerdo','superior_direito_inferior_esquerdo','inferior_esquerdo_superior_direito'])],
            'ativo' => ['required','boolean'], 'certificado_a1' => ['nullable','integer',Rule::exists('certificados_a1','id')->whereNull('apagado_em')],
            'largura' => ['nullable','integer','min:1'], 'altura' => ['nullable','integer','min:1'], 'pagina' => ['nullable',Rule::in(['A4','Carta','Oficio','Personalizado'])], 'layout_pagina' => ['nullable',Rule::in(['Retrato','Paisagem'])],
        ]);
    }
    private function normalizePage(array $data): array
    {
        $data['pagina'] ??= 'A4';
        $data['layout_pagina'] ??= 'Paisagem';
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
    private function duplicateBackground(?string $filename, array &$createdFiles): ?string
    {
        if (blank($filename)) return null;
        if (basename((string) $filename) !== $filename) throw new \RuntimeException('Nome de arquivo de fundo inválido.');
        $source = public_path('certificado/imagem_fundo/'.$filename);
        if (! is_file($source)) throw new \RuntimeException('Arquivo de fundo não encontrado: '.$filename);
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        $name = hash('sha1', Str::uuid()->toString()).($extension !== '' ? '.'.$extension : '');
        $directory = public_path('certificado/imagem_fundo');
        File::ensureDirectoryExists($directory);
        $destination = $directory.'/'.$name;
        if (! File::copy($source, $destination)) throw new \RuntimeException('Não foi possível copiar o arquivo de fundo.');
        $createdFiles[] = $destination;
        return $name;
    }
    private function createColoredBackground(string $color, int $widthMm, int $heightMm): string
    {
        $width = max((int) round($widthMm / 25.4 * 96), 1); $height = max((int) round($heightMm / 25.4 * 96), 1);
        $image = imagecreatetruecolor($width, $height); [$red, $green, $blue] = sscanf($color, '#%02x%02x%02x');
        imagefill($image, 0, 0, imagecolorallocate($image, $red, $green, $blue));
        $name = hash('sha1', Str::uuid()->toString()).'.png'; $directory = public_path('certificado/imagem_fundo'); File::ensureDirectoryExists($directory);
        imagepng($image, $directory.'/'.$name); imagedestroy($image); return $name;
    }
    private function createGradientBackground(string $startColor, string $endColor, string $direction, int $widthMm, int $heightMm): string
    {
        $width = max((int) round($widthMm / 25.4 * 96), 1); $height = max((int) round($heightMm / 25.4 * 96), 1);
        $image = imagecreatetruecolor($width, $height); $start = sscanf($startColor, '#%02x%02x%02x'); $end = sscanf($endColor, '#%02x%02x%02x');
        $colors = []; for ($step = 0; $step <= 512; $step++) { $ratio = $step / 512; $colors[$step] = imagecolorallocate($image, (int) round($start[0] + ($end[0] - $start[0]) * $ratio), (int) round($start[1] + ($end[1] - $start[1]) * $ratio), (int) round($start[2] + ($end[2] - $start[2]) * $ratio)); }
        for ($y = 0; $y < $height; $y++) for ($x = 0; $x < $width; $x++) {
            $nx = $width > 1 ? $x / ($width - 1) : 0; $ny = $height > 1 ? $y / ($height - 1) : 0;
            $diagonal = max(($width * $width) + ($height * $height), 1);
            $ratio = match ($direction) { 'baixo_cima' => 1-$ny, 'esquerda_direita' => $nx, 'direita_esquerda' => 1-$nx, 'superior_esquerdo_inferior_direito' => (($nx*$width*$width)+($ny*$height*$height))/$diagonal, 'inferior_direito_superior_esquerdo' => 1-((($nx*$width*$width)+($ny*$height*$height))/$diagonal), 'superior_direito_inferior_esquerdo' => (((1-$nx)*$width*$width)+($ny*$height*$height))/$diagonal, 'inferior_esquerdo_superior_direito' => (($nx*$width*$width)+((1-$ny)*$height*$height))/$diagonal, default => $ny };
            imagesetpixel($image, $x, $y, $colors[(int) round($ratio * 512)]);
        }
        $name = hash('sha1', Str::uuid()->toString()).'.png'; $directory = public_path('certificado/imagem_fundo'); File::ensureDirectoryExists($directory);
        imagepng($image, $directory.'/'.$name); imagedestroy($image); return $name;
    }
    private function removeBackground(?string $name): void { if (filled($name) && basename($name)===$name) File::delete(public_path('certificado/imagem_fundo/'.$name)); }
    private function fileDataUri(?string $path): ?string
    {
        if (! $path || ! is_file($path)) return null;
        $mime = File::mimeType($path) ?: 'image/png';
        return 'data:'.$mime.';base64,'.base64_encode((string) File::get($path));
    }

    private function previewContext($participant, ?Atividade $activity, ?Responsavel $responsible, ?string $rubricaPath): array
    {
        return [
            'participante'=>['nome'=>$participant?->nome ?? 'Nome do participante','email'=>$participant?->email ?? 'email@exemplo.com','cpf'=>$participant?->cpf ?? '000.000.000-00'],
            'evento'=>['nome'=>$activity?->evento?->nome ?? 'Nome do evento','descricao'=>$activity?->evento?->descricao ?? ''],
            'atividade'=>['nome'=>$activity?->nome ?? 'Nome da atividade','carga_horaria'=>'60 horas'],
            'responsavel'=>['nome'=>$responsible?->participante?->nome ?? 'Nome do responsável','cargo'=>$responsible?->cargo ?? 'Instrutor','titulacao'=>$responsible?->titulacao ?? '', 'rubrica_path'=>$rubricaPath],
            'emissao'=>['nome'=>'Emissão de teste','data'=>now()->format('d/m/Y')], 'certificado'=>['codigo'=>'TESTE-000001'],
            'link_validacao'=>route('certificadosnovos.public.pdf','TESTE-000001'),
        ];
    }
}
