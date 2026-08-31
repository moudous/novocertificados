<?php

namespace App\Services;

use App\Models\CertificadoA1;
use Barryvdh\DomPDF\PDF;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use setasign\Fpdi\Tcpdf\Fpdi;

class PdfDigitalSigner
{
    public function output(PDF $pdf, ?CertificadoA1 $certificate, string $reason='Certificado digital'): string
    {
        $unsigned=$pdf->output();
        if(!$certificate)return $unsigned;
        $path=$certificate->certificatePath();
        if(!$path)throw ValidationException::withMessages(['certificado_a1'=>'O certificado A1 selecionado não possui um arquivo PFX/P12 disponível.']);
        if($certificate->valido_de?->isFuture()||$certificate->valido_ate?->isPast())throw ValidationException::withMessages(['certificado_a1'=>'O certificado A1 selecionado está fora do período de validade.']);
        $credentials=[];
        if(!@openssl_pkcs12_read((string)file_get_contents($path),$credentials,(string)$certificate->senha)||empty($credentials['cert'])||empty($credentials['pkey']))throw ValidationException::withMessages(['certificado_a1'=>'Não foi possível abrir o certificado A1 selecionado com a senha armazenada.']);

        $directory=storage_path('framework/cache/pdf-signatures');
        File::ensureDirectoryExists($directory,0700,true);
        $token=bin2hex(random_bytes(16));
        $source=$directory.'/'.$token.'.pdf';$certFile=$directory.'/'.$token.'.crt';$keyFile=$directory.'/'.$token.'.key';$chainFile=$directory.'/'.$token.'.chain.crt';
        File::put($source,$unsigned);File::put($certFile,$credentials['cert']);File::put($keyFile,$credentials['pkey']);
        @chmod($certFile,0600);@chmod($keyFile,0600);
        $extra=collect($credentials['extracerts']??[])->filter()->implode("\n");
        if($extra!==''){File::put($chainFile,$extra);@chmod($chainFile,0600);}
        try {
            $signed=new Fpdi();
            $signed->setPrintHeader(false);$signed->setPrintFooter(false);$signed->SetMargins(0,0,0);$signed->SetAutoPageBreak(false,0);
            $signed->setSignature('file://'.$certFile,'file://'.$keyFile,'',$extra!==''?$chainFile:'',2,['Name'=>$certificate->titular?:$certificate->nome,'Location'=>'Brasil','Reason'=>$reason,'ContactInfo'=>'']);
            $pageCount=$signed->setSourceFile($source);
            for($page=1;$page<=$pageCount;$page++){
                $template=$signed->importPage($page);$size=$signed->getTemplateSize($template);
                $signed->AddPage($size['orientation'],[$size['width'],$size['height']]);
                $signed->useTemplate($template,0,0,$size['width'],$size['height'],true);
            }
            $signed->setSignatureAppearance(0,0,0,0,1,'Assinatura digital');
            return $signed->Output('certificado-assinado.pdf','S');
        } finally { File::delete([$source,$certFile,$keyFile,$chainFile]); }
    }
}
