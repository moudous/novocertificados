<?php

namespace App\Services;

use App\Models\ListaParticipante;
use App\Models\Template;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CertificadoImageGenerator
{
    public function __construct(
        private readonly TemplateLayoutRenderer $renderer,
        private readonly CertificadoPdfGenerator $pdfGenerator,
    ) {}

    public function generate(ListaParticipante $item): void
    {
        if (! $item->arquivoExists()) $this->pdfGenerator->generate($item);
        $item->refresh()->loadMissing('novoCertificado.template.imagemBiblioteca');
        $template = $item->novoCertificado?->template;
        abort_unless($template, 422, 'A emissão precisa ter um template antes da geração.');

        $code = $item->codigo_img ?: strtoupper(Str::random(24));
        $directory = storage_path('app/private/certificados-imagens');
        File::ensureDirectoryExists($directory);
        $filename = 'certificado-'.$item->id.'-'.$code.'.png';
        File::put($directory.'/'.$filename, $this->render($template, $item->snapshot_template ?? $template->elementos_layout ?? [], $item->snapshot_dados ?? []));
        $item->update(['codigo_img'=>$code, 'arquivo_img'=>$filename]);
    }

    public function render(Template $template, array $layout, array $context): string
    {
        $scale = 4;
        $width = max((int) round(max((float) $template->largura, 1) * $scale), 1);
        $height = max((int) round(max((float) $template->altura, 1) * $scale), 1);
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        $this->drawDataImage($canvas, $this->renderer->background($template), 0, 0, $width, $height);

        foreach ($this->renderer->elements($layout, $context) as $element) {
            $x = (int) round($element['x'] * $scale); $y = (int) round($element['y'] * $scale);
            $w = max((int) round($element['width'] * $scale), 1); $h = max((int) round($element['height'] * $scale), 1);
            if ($element['type'] === 'image') {
                $this->drawDataImage($canvas, $element['image'], $x, $y, $w, $h, (int) $element['rotation']);
                continue;
            }
            $text = $element['type'] === 'rich_text'
                ? html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $element['html'])), ENT_QUOTES | ENT_HTML5, 'UTF-8')
                : $element['text'];
            $this->drawText($canvas, $text, $element, $x, $y, $w, $h);
        }

        ob_start(); imagepng($canvas, null, 6); $png = (string) ob_get_clean(); imagedestroy($canvas);
        return $png;
    }

    private function drawDataImage(\GdImage $canvas, ?string $dataUri, int $x, int $y, int $width, int $height, int $rotation = 0): void
    {
        if (! $dataUri || ! str_contains($dataUri, ',')) return;
        $binary = base64_decode(substr($dataUri, strpos($dataUri, ',') + 1), true);
        $source = $binary === false ? false : @imagecreatefromstring($binary);
        if (! $source) return;
        if ($rotation) { $rotated = imagerotate($source, 360-$rotation, imagecolorallocatealpha($source, 0, 0, 0, 127)); imagedestroy($source); $source=$rotated; }
        imagecopyresampled($canvas, $source, $x, $y, 0, 0, $width, $height, imagesx($source), imagesy($source));
        imagedestroy($source);
    }

    private function drawText(\GdImage $canvas, string $text, array $element, int $x, int $y, int $width, int $height): void
    {
        $font = $this->fontPath((string) $element['font_family'], (bool) $element['bold'], (bool) $element['italic']);
        $size = max((float) $element['font_size'] * 1.333, 1);
        [$r,$g,$b] = sscanf($element['color'], '#%02x%02x%02x'); $color=imagecolorallocate($canvas,$r,$g,$b);
        $lines=[];
        foreach ((preg_split('/\R/u',$text) ?: ['']) as $paragraph) {
            $line='';
            foreach ((preg_split('/\s+/u',trim($paragraph)) ?: []) as $word) {
                $test=$line===''?$word:$line.' '.$word;
                $box=imagettfbbox($size,0,$font,$test);
                if($line!==''&&($box[2]-$box[0])>$width){$lines[]=$line;$line=$word;}else{$line=$test;}
            }
            $lines[]=$line;
        }
        $lineHeight=(int)ceil($size*1.25);$baseline=$y+(int)ceil($size);
        foreach($lines as $line){if($baseline>$y+$height)break;$box=imagettfbbox($size,0,$font,$line);$lineWidth=$box[2]-$box[0];$left=match($element['align']){'direita'=>$x+$width-$lineWidth,'centralizado'=>$x+(int)(($width-$lineWidth)/2),default=>$x};imagettftext($canvas,$size,0,$left,$baseline,$color,$font,$line);if($element['underline'])imageline($canvas,$left,$baseline+2,$left+$lineWidth,$baseline+2,$color);$baseline+=$lineHeight;}
    }

    private function fontPath(string $family, bool $bold, bool $italic): string
    {
        $serif = in_array($family, ['Times New Roman','Georgia','Garamond'], true);
        $mono = $family === 'Courier New';
        $base = $mono ? 'DejaVuSansMono' : ($serif ? 'DejaVuSerif' : 'DejaVuSans');
        $suffix = $bold && $italic ? ($serif?'-BoldItalic':'-BoldOblique') : ($bold ? '-Bold' : ($italic ? ($serif?'-Italic':'-Oblique') : ''));
        $path = base_path('vendor/dompdf/dompdf/lib/fonts/'.$base.$suffix.'.ttf');
        return is_file($path) ? $path : base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf');
    }
}
