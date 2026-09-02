<?php

namespace App\Services;

use App\Models\ListaParticipante;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CertificadoPdfGenerator
{
    public function __construct(private readonly TemplateLayoutRenderer $renderer,private readonly PdfDigitalSigner $signer) {}

    public function generate(ListaParticipante $item): void
    {
        $item->loadMissing([
            'participante',
            'novoCertificado.template.imagemBiblioteca','novoCertificado.template.certificadoA1',
            'novoCertificado.evento','novoCertificado.atividade',
            'novoCertificado.responsavel.participante',
            'novoCertificado.rubrica',
        ]);

        $emissao = $item->novoCertificado;
        abort_unless($emissao?->template, 422, 'A emissão precisa ter um template antes da geração.');

        try {
            $code = $item->codigo ?: strtoupper(Str::random(16));
            $responsavel = $emissao->responsavel;
            $rubrica = $emissao->rubrica ?: $responsavel?->participante?->rubricas()->where('ativo', true)->first();
            $context = [
                'participante' => ['nome' => $item->participante?->nome, 'email' => $item->participante?->email, 'cpf' => $item->participante?->cpf],
                'evento' => ['nome' => $emissao->evento?->nome, 'descricao' => $emissao->evento?->descricao],
                'atividade' => ['nome' => $emissao->atividade?->nome, 'carga_horaria' => data_get($item->dados_personalizados, 'carga_horaria', $emissao->carga_horaria)],
                'responsavel' => ['nome' => $responsavel?->participante?->nome, 'cargo' => $responsavel?->cargo, 'titulacao' => $responsavel?->titulacao, 'rubrica_path' => $this->renderer->rubricaPath($rubrica)],
                'emissao' => ['nome' => $emissao->nome, 'data' => ($emissao->data_emissao ?: now())->format('d/m/Y')],
                'certificado' => ['codigo' => $code],
                'template' => $item->dados_personalizados ?? [],
                'link_validacao' => route('certificadosnovos.public.pdf', $code),
            ];
            $template = $emissao->template;
            $width = max($template->largura, 1);
            $height = max($template->altura, 1);
            $pdf = Pdf::loadView('templates.preview-pdf', [
                'template' => $template,
                'elements' => collect($this->renderer->elements($template->elementos_layout ?? [], $context)),
                'width' => $width,
                'height' => $height,
                'background' => $this->renderer->background($template),
                'fonts' => collect($this->renderer->fonts()),
            ])->setPaper([0, 0, $width * 2.834645669, $height * 2.834645669]);
            $directory = public_path('certificado/emitidos');
            File::ensureDirectoryExists($directory);
            $name = 'certificado-'.$item->id.'-'.$code.'.pdf';
            File::put($directory.'/'.$name, $this->signer->output($pdf,$template->certificadoA1,'Emissão de certificado digital'));
            $item->update(['codigo' => $code, 'arquivo_pdf' => $name, 'snapshot_dados' => $context, 'snapshot_template' => $template->elementos_layout, 'gerado_em' => now(), 'erro_geracao' => null]);
        } catch (\Throwable $exception) {
            report($exception);
            $item->update(['erro_geracao' => Str::limit($exception->getMessage(), 1000), 'gerado_em' => null]);
            throw $exception;
        }
    }
}
