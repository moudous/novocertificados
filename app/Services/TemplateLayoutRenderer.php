<?php

namespace App\Services;

use App\Models\BibliotecaImagem;
use App\Models\FonteLayout;
use App\Models\RubricaParticipante;
use App\Models\Template;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class TemplateLayoutRenderer
{
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
        return collect($layout)->take(200)->map(function (array $item) use ($context, $library): ?array {
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
                'image'=>null, 'text'=>'', 'html'=>'',
            ];
            if ($type === 'image') {
                $path = null;
                if (($item['source_type']??'') === 'library') $path = $library->get((int)($item['library_image_id']??0))?->path();
                if (($item['source_type']??'') === 'responsible_signature') $path = $context['responsavel']['rubrica_path'] ?? null;
                $element['image'] = $this->fileDataUri($path);
            } else {
                $content = (string)($item['content']??$item['text']??'');
                if (($item['source_type']??'fixed') === 'dynamic') $content = '{{ '.($item['source_key']??'').' }}';
                $resolved = $this->resolveTokens($content, $context);
                if ($type === 'rich_text') $element['html'] = $this->sanitizeRichText($resolved);
                else $element['text'] = trim(strip_tags($resolved));
            }
            return $element;
        })->filter()->values()->all();
    }

    public function resolveTokens(string $content, array $context): string
    {
        return preg_replace_callback('/\{\{\s*([a-z_]+\.[a-z_]+)\s*\}\}/i', function (array $match) use ($context): string {
            [$group,$field] = explode('.', $match[1], 2);
            $fallback = '['.(self::SOURCES[$match[1]] ?? $match[1]).']';
            return e((string) data_get($context, $group.'.'.$field, $fallback));
        }, $content) ?? $content;
    }

    public function sanitizeRichText(string $html): string
    {
        $html = strip_tags($html, '<strong><b><em><i><u><br><span>');
        $html = preg_replace('/<(?!span\b)(\w+)[^>]*>/i', '<$1>', $html) ?? $html;
        $html = preg_replace_callback('/<span\b([^>]*)>/i', function (array $match): string {
            preg_match('/style\s*=\s*["\']([^"\']*)["\']/i', $match[1], $style);
            $allowed = [];
            if (preg_match('/color\s*:\s*(#[0-9a-f]{6})/i', $style[1]??'', $color)) $allowed[]='color:'.$color[1];
            if (preg_match('/font-size\s*:\s*([0-9]{1,3}(?:\.[0-9]+)?(?:pt|px))/i', $style[1]??'', $size)) $allowed[]='font-size:'.$size[1];
            return $allowed ? '<span style="'.implode(';',$allowed).'">' : '<span>';
        }, $html) ?? $html;
        return $html;
    }

    public function fonts(): array
    {
        return FonteLayout::query()->get()->map(fn (FonteLayout $font): array => ['name'=>$font->nome,'data'=>$this->fileDataUri($font->path())])->filter(fn(array $font)=>filled($font['data']))->values()->all();
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

    private function fileDataUri(?string $path): ?string
    {
        if (!$path || !is_file($path)) return null;
        return 'data:'.(File::mimeType($path)?:'image/png').';base64,'.base64_encode((string)File::get($path));
    }
}
