<?php

namespace App\Services;

use App\Models\CertificadoA1;
use Barryvdh\DomPDF\PDF;
use Dompdf\Adapter\CPDF as CpdfCanvas;
use Dompdf\Cpdf as CpdfEngine;
use Illuminate\Validation\ValidationException;

class PdfDigitalSigner
{
    public function sign(PDF $pdf,?CertificadoA1 $certificate,string $reason='Certificado digital'): PDF
    {
        if(!$certificate)return $pdf;
        $path=$certificate->certificatePath();
        if(!$path)throw ValidationException::withMessages(['certificado_a1'=>'O certificado A1 selecionado não possui um arquivo PFX/P12 disponível.']);
        if($certificate->valido_de?->isFuture()||$certificate->valido_ate?->isPast())throw ValidationException::withMessages(['certificado_a1'=>'O certificado A1 selecionado está fora do período de validade.']);
        $credentials=[];
        if(!@openssl_pkcs12_read((string)file_get_contents($path),$credentials,(string)$certificate->senha)||empty($credentials['cert'])||empty($credentials['pkey']))throw ValidationException::withMessages(['certificado_a1'=>'Não foi possível abrir o certificado A1 selecionado com a senha armazenada.']);
        $pdf->render();$canvas=$pdf->getDomPDF()->getCanvas();
        if(!$canvas instanceof CpdfCanvas)throw ValidationException::withMessages(['certificado_a1'=>'O mecanismo atual de PDF não oferece suporte à assinatura digital.']);
        $cpdf=$canvas->get_cpdf();$nodeFonts=array_values((array)($cpdf->objects[$cpdf->currentNode]['info']['fonts']??[]));
        if($nodeFonts){$cpdf->objects[$cpdf->currentNode]['info']['fonts']=$nodeFonts;$cpdf->currentFontNum=$nodeFonts[0]['fontNum'];}
        $cpdf->addForm(3,false);$field=$cpdf->addFormField(CpdfEngine::ACROFORM_FIELD_SIG,'AssinaturaDigital'.bin2hex(random_bytes(4)),0,0,0,0,0);
        $signature=$cpdf->addSignature($credentials['cert'],$credentials['pkey'],'',$certificate->titular?:$certificate->nome,null,$reason,null);$cpdf->setFormFieldRefValue($field,$signature);
        return $pdf;
    }
}
