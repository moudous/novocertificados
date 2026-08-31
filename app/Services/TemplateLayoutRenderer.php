<?php

namespace App\Services;

use App\Models\BibliotecaImagem;
use App\Models\FonteLayout;
use App\Models\ImagemTemplate;
use App\Models\RubricaParticipante;
use App\Models\Template;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class TemplateLayoutRenderer
{
    public const QR_STYLES = [
        'classic' => 'Clássico preto',
        'blue' => 'Azul institucional',
        'green' => 'Verde',
    ];
    public const FALLBACK_FONTS = [
        'Arial' => 'DejaVuSans.ttf', 'Helvetica' => 'DejaVuSans.ttf',
        'Verdana' => 'DejaVuSans.ttf', 'Trebuchet MS' => 'DejaVuSans.ttf', 'Tahoma' => 'DejaVuSans.ttf',
        'Times New Roman' => 'DejaVuSerif.ttf', 'Georgia' => 'DejaVuSerif.ttf', 'Garamond' => 'DejaVuSerif.ttf',
        'Courier New' => 'DejaVuSansMono.ttf',
    ];

    public const SOURCES = [
        'participante.nome' => 'Participante · Nome', 'participante.email' => 'Participante · E-mail', 'participante.cpf' => 'Participante · CPF',
        'evento.nome' => 'Evento · Nome', 'evento.descricao' => 'Evento · Descrição',
        'atividade.nome' => 'Atividade · Nome', 'atividade.carga_horaria' => 'Atividade · Carga horária',
        'responsavel.nome' => 'Responsável · Nome', 'responsavel.cargo' => 'Responsável · Cargo', 'responsavel.titulacao' => 'Responsável · Titulação',
        'emissao.nome' => 'Emissão · Nome', 'emissao.data' => 'Emissão · Data', 'certificado.codigo' => 'Certificado · Código',
    ];

    public function elements(array $layout, array $context = []): array
    {
        $library = BibliotecaImagem::withTrashed()->whereIn('id', collect($layout)->pluck('library_image_id')->filter()->unique())->get()->keyBy('id');
        $templateImages = ImagemTemplate::query()->whereIn('id',collect($layout)->pluck('template_image_id')->filter()->unique())->get()->keyBy('id');
        $signatures = RubricaParticipante::withTrashed()->whereIn('id', collect($layout)->pluck('rubrica_id')->filter()->unique())->get()->keyBy('id');
        return collect($layout)->take(200)->map(function (array $item) use ($context, $library, $templateImages, $signatures): ?array {
            $type = (string) ($item['type'] ?? 'text');
            if (! in_array($type, ['text','rich_text','image'], true)) return null;
            $element = [
                'type'=>$type, 'x'=>max((float)($item['x']??0),0), 'y'=>max((float)($item['y']??0),0),
                'width'=>max((float)($item['width']??1),1), 'height'=>max((float)($item['height']??1),1),
                'color'=>preg_match('/^#[0-9a-f]{6}$/i',(string)($item['color']??''))?$item['color']:'#111827',
                'align'=>in_array(($item['align']??''),['esquerda','direita','centralizado','justificado'],true)?$item['align']:'esquerda',
                'font_family'=>Str::limit(preg_replace('/[^\pL\pN _-]/u','',(string)($item['font_family']??'Arial'))?:'Arial',100,''),
                'font_size'=>min(max((float)($item['font_size']??12),1),300), 'bold'=>(bool)($item['bold']??false),
                'italic'=>(bool)($item['italic']??false), 'underline'=>(bool)($item['underline']??false),
                'rotation'=>in_array((int)($item['rotation']??0),[0,90,180,270],true)?(int)($item['rotation']??0):0,
                'image'=>null, 'link'=>null, 'text'=>'', 'html'=>'',
            ];
            if ($type === 'image') {
                $path = null;
                if (($item['source_type']??'') === 'library') $path = $library->get((int)($item['library_image_id']??0))?->path();
                if (($item['source_type']??'') === 'template_image') $element['image'] = $templateImages->get((int)($item['template_image_id']??0))?->dataUrl();
                if (($item['source_type']??'') === 'responsible_signature') {
                    $signature = $signatures->get((int)($item['rubrica_id']??0));
                    $path = $signature?->signatureExists() ? public_path('certificado/rubricas_participantes/'.$signature->rubrica) : ($context['responsavel']['rubrica_path'] ?? null);
                }
                if (($item['source_type']??'') === 'validation_qr') {
                    $validationUrl = (string) ($context['link_validacao'] ?? '');
                    if (preg_match('#^https?://[^\s<>]+$#i', $validationUrl)) {
                        $element['image'] = $this->validationQrDataUri($validationUrl, (string) ($item['qr_style'] ?? 'classic'));
                        $element['link'] = $validationUrl;
                    }
                }
                if(!$element['image'])$element['image'] = $this->fileDataUri($path);
            } else {
                $content = (string)($item['content']??$item['text']??'');
                if (($item['source_type']??'fixed') === 'dynamic') $content = '{{ '.($item['source_key']??'').' }}';
                if (($item['source_type']??'fixed') === 'validation_link' && trim($content) === '') $content = '<a href="{{link_validacao}}">{{link_validacao}}</a>';
                $resolved = $this->resolveTokens($content, $context);
                if ($type === 'rich_text') $element['html'] = $this->sanitizeRichText($resolved);
                else $element['text'] = trim(strip_tags($resolved));
            }
            return $element;
        })->filter()->values()->all();
    }

    public function resolveTokens(string $content, array $context): string
    {
        return preg_replace_callback('/\{\{\s*((?:[a-z_]+\.[a-z_]+)|link_validacao)\s*\}\}/i', function (array $match) use ($context): string {
            $key = strtolower($match[1]);
            $fallback = '['.(self::SOURCES[$key] ?? ($key === 'link_validacao' ? 'Link de validação' : $key)).']';
            return e((string) data_get($context, $key, $fallback));
        }, $content) ?? $content;
    }

    public function sanitizeRichText(string $html): string
    {
        $html = strip_tags($html, '<strong><b><em><i><u><br><span><a>');
        $html = preg_replace('/<(?!span\b|a\b)(\w+)[^>]*>/i', '<$1>', $html) ?? $html;
        $html = preg_replace_callback('/<span\b([^>]*)>/i', function (array $match): string {
            preg_match('/style\s*=\s*["\']([^"\']*)["\']/i', $match[1], $style);
            $allowed = [];
            if (preg_match('/color\s*:\s*(#[0-9a-f]{6})/i', $style[1]??'', $color)) $allowed[]='color:'.$color[1];
            if (preg_match('/font-size\s*:\s*([0-9]{1,3}(?:\.[0-9]+)?(?:pt|px))/i', $style[1]??'', $size)) $allowed[]='font-size:'.$size[1];
            return $allowed ? '<span style="'.implode(';',$allowed).'">' : '<span>';
        }, $html) ?? $html;
        $html = preg_replace_callback('/<a\b([^>]*)>/i', function (array $match): string {
            preg_match('/href\s*=\s*["\']([^"\']*)["\']/i', $match[1], $href);
            $url = html_entity_decode($href[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return preg_match('#^https?://[^\s<>]+$#i', $url) ? '<a href="'.e($url).'">' : '<a>';
        }, $html) ?? $html;
        return $html;
    }

    public function fonts(): array
    {
        $uploaded = FonteLayout::query()->get()->map(fn (FonteLayout $font): array => ['name'=>$font->nome,'data'=>$this->fileDataUri($font->path())]);
        $fallbacks = collect(self::FALLBACK_FONTS)->map(fn (string $file, string $name): array => [
            'name' => $name,
            'data' => $this->fileDataUri(base_path('vendor/dompdf/dompdf/lib/fonts/'.$file)),
        ]);

        return $uploaded->concat($fallbacks)->filter(fn(array $font)=>filled($font['data']))->unique('name')->values()->all();
    }

    public function background(Template $template): ?string
    {
        if ($template->tipo_fundo === 'biblioteca') return $this->fileDataUri($template->imagemBiblioteca?->path());
        $filename = $template->activeBackgroundFilename();
        return $this->fileDataUri($filename ? public_path('certificado/imagem_fundo/'.$filename) : null);
    }

    public function rubricaPath(?RubricaParticipante $rubrica): ?string
    {
        return $rubrica?->signatureExists() ? public_path('certificado/rubricas_participantes/'.$rubrica->rubrica) : null;
    }

    public function validationQrDataUri(string $url, string $style = 'classic'): string
    {
        $colors = [
            'classic' => new Color(0, 0, 0),
            'blue' => new Color(13, 71, 161),
            'green' => new Color(20, 105, 65),
        ];
        $qrCode = QrCode::create($url)
            ->setSize(360)
            ->setMargin(16)
            ->setErrorCorrectionLevel(ErrorCorrectionLevel::High)
            ->setForegroundColor($colors[$style] ?? $colors['classic']);

        return (new PngWriter())->write($qrCode)->getDataUri();
    }

    private function fileDataUri(?string $path): ?string
    {
        if (!$path || !is_file($path)) return null;
        return 'data:'.(File::mimeType($path)?:'image/png').';base64,'.base64_encode((string)File::get($path));
    }
}
