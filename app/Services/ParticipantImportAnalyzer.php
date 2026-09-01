<?php

namespace App\Services;

use App\Models\NovoCertificado;
use App\Models\Participante;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ParticipantImportAnalyzer
{
    public function analyze(NovoCertificado $emission,array $rows): array
    {
        $participants=Participante::query()->orderBy('id')->get(['id','nome','email','sexo','cpf','grupo']);
        $byName=$participants->groupBy(fn($p)=>$this->name($p->nome));
        $byEmail=$participants->filter(fn($p)=>filled($p->email))->groupBy(fn($p)=>$this->email($p->email));
        $linked=DB::table('lista_participantes as lp')->join('novos_certificados as nc','nc.id','=','lp.novo_certificado_id')->where('nc.template_id',$emission->template_id)->pluck('lp.participante_id')->map(fn($id)=>(int)$id)->flip();
        $seen=[];$analysis=[];
        foreach($rows as $index=>$source){
            $name=trim((string)($source['nome']??''));$email=trim((string)($source['email']??''));$nameKey=$this->name($name);$emailKey=$this->email($email);
            $row=[...$source,'index'=>$index,'line'=>$source['line']??$index+2,'nome'=>$name,'email'=>$email,'sexo'=>trim((string)($source['sexo']??'')),'cpf'=>preg_replace('/\D+/','',(string)($source['cpf']??'')),'grupo'=>trim((string)($source['grupo']??'')),'existing_id'=>null,'existing_label'=>null,'kind'=>'','status'=>'','requires_action'=>false];
            $duplicateKey=$nameKey.'|'.$emailKey;
            if($nameKey!==''&&$emailKey!==''&&isset($seen[$duplicateKey])){$row['kind']='repeated';$row['status']='Repetido na planilha — não importado';$analysis[]=$row;continue;}
            if($nameKey!==''&&$emailKey!=='')$seen[$duplicateKey]=true;
            if($nameKey===''||$emailKey===''||filter_var($email,FILTER_VALIDATE_EMAIL)===false){$row['kind']='incomplete';$row['status']=$emailKey!==''&&filter_var($email,FILTER_VALIDATE_EMAIL)===false?'Não importado — e-mail inválido':'Não importado — cadastro incompleto';$row['requires_action']=true;$analysis[]=$row;continue;}
            $nameMatches=$byName->get($nameKey,collect());$emailMatches=$byEmail->get($emailKey,collect());$exact=$nameMatches->first(fn($participant)=>$this->email($participant->email)===$emailKey);
            if($exact){$row['existing_id']=$exact->id;$row['existing_label']='#'.$exact->id.' · '.$exact->nome.' · '.$exact->email;if(isset($linked[$exact->id])){$row['kind']='linked';$row['status']='Participante existente não importado';}else{$row['kind']='recovered';$row['status']='Recuperado da lista';}$analysis[]=$row;continue;}
            if($nameMatches->isEmpty()&&$emailMatches->isEmpty()){$row['kind']='new';$row['status']='Será adicionado';$analysis[]=$row;continue;}
            $candidate=$nameMatches->first()?:$emailMatches->first();$row['existing_id']=$candidate?->id;$row['existing_label']=$candidate?'#'.$candidate->id.' · '.$candidate->nome.' · '.($candidate->email?:'sem e-mail'):null;$row['kind']='conflict';$row['status']=$nameMatches->isNotEmpty()?'Nome já existente com outro e-mail':'E-mail já existente com outro nome';$row['requires_action']=true;$analysis[]=$row;
        }
        $collection=collect($analysis);
        return ['rows'=>$analysis,'summary'=>['total'=>$collection->count(),'new'=>$collection->where('kind','new')->count(),'recovered'=>$collection->where('kind','recovered')->count(),'linked'=>$collection->where('kind','linked')->count(),'repeated'=>$collection->where('kind','repeated')->count(),'incomplete'=>$collection->where('kind','incomplete')->count(),'conflicts'=>$collection->where('kind','conflict')->count(),'ready'=>$collection->whereIn('kind',['new','recovered'])->count()]];
    }
    private function name(?string $value): string { return preg_replace('/\s+/u',' ',Str::lower(trim((string)$value)))??''; }
    private function email(?string $value): string { return Str::lower(trim((string)$value)); }
}
