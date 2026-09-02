<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Process\Process;
use Illuminate\Validation\ValidationException;

class ParticipantSpreadsheetReader
{
    public function read(UploadedFile $file, array $extraHeaders = []): array
    {
        $extension=strtolower($file->getClientOriginalExtension());
        $matrix=match($extension){
            'csv'=>$this->csv($file->getRealPath()),
            'xls'=>$this->spreadsheet($file->getRealPath()),
            'xlsx'=>$this->xlsx($file->getRealPath()),
            'ods','odt'=>$this->openDocument($file->getRealPath()),
            default=>throw ValidationException::withMessages(['planilha'=>'Formato de planilha não suportado.']),
        };
        $matrix=array_values(array_filter($matrix,fn($row)=>collect($row)->contains(fn($value)=>trim((string)$value)!=='')));
        if(!$matrix)throw ValidationException::withMessages(['planilha'=>'A planilha está vazia.']);
        $headers=array_map(fn($value)=>$this->header((string)$value),array_shift($matrix));
        if(!in_array('nome',$headers,true)||!in_array('email',$headers,true))throw ValidationException::withMessages(['planilha'=>'A primeira linha deve conter obrigatoriamente as colunas nome e email.']);
        $missing=array_values(array_diff($extraHeaders,$headers));
        if($missing)throw ValidationException::withMessages(['planilha'=>'A planilha não possui os campos dinâmicos obrigatórios: '.implode(', ',$missing).'.']);
        $allowed=array_merge(['nome','email','sexo','cpf','grupo','carga_horaria'],$extraHeaders);$rows=[];
        foreach(array_slice($matrix,0,2000) as $number=>$values){$row=[];foreach($headers as $index=>$header)if(in_array($header,$allowed,true))$row[$header]=trim((string)($values[$index]??''));if(collect($row)->filter()->isNotEmpty())$rows[]=['line'=>$number+2,...$row];}
        if(!$rows)throw ValidationException::withMessages(['planilha'=>'Nenhum participante foi encontrado na planilha.']);
        return $rows;
    }

    private function csv(string $path): array
    {
        $handle=fopen($path,'rb');$first=(string)fgets($handle);rewind($handle);$delimiter=substr_count($first,';')>=substr_count($first,',')?';':',';$rows=[];while(($row=fgetcsv($handle,0,$delimiter))!==false)$rows[]=$row;fclose($handle);return $rows;
    }
    private function spreadsheet(string $path): array { return IOFactory::load($path)->getActiveSheet()->toArray(null,true,true,false); }
    private function archive(string $path,string $entry,bool $optional=false): string { $process=new Process(['unzip','-p',$path,$entry]);$process->setTimeout(20);$process->run();if(!$process->isSuccessful()){if($optional)return '';throw ValidationException::withMessages(['planilha'=>'Não foi possível ler a estrutura compactada da planilha.']);}return $process->getOutput(); }
    private function xlsx(string $path): array
    {
        $shared=[];$sharedXml=$this->archive($path,'xl/sharedStrings.xml',true);if($sharedXml!==''){$dom=new \DOMDocument();@$dom->loadXML($sharedXml);foreach($dom->getElementsByTagName('si') as $item)$shared[]=trim($item->textContent);}
        $xml=$this->archive($path,'xl/worksheets/sheet1.xml');$dom=new \DOMDocument();if(!@$dom->loadXML($xml))throw ValidationException::withMessages(['planilha'=>'A primeira aba do XLSX é inválida.']);$rows=[];
        foreach($dom->getElementsByTagName('row') as $row){$values=[];foreach($row->getElementsByTagName('c') as $cell){$reference=$cell->getAttribute('r');preg_match('/^[A-Z]+/',$reference,$match);$column=$this->columnIndex($match[0]??'A');$value=$cell->getElementsByTagName('v')->item(0)?->textContent??'';if($cell->getAttribute('t')==='s')$value=$shared[(int)$value]??'';elseif($cell->getAttribute('t')==='inlineStr')$value=$cell->textContent;$values[$column]=$value;}if($values){$max=max(array_keys($values));$rows[]=array_map(fn($i)=>$values[$i]??'',range(0,$max));}}
        return $rows;
    }
    private function openDocument(string $path): array
    {
        $xml=$this->archive($path,'content.xml');$dom=new \DOMDocument();if(!@$dom->loadXML($xml))throw ValidationException::withMessages(['planilha'=>'O documento ODS/ODT é inválido.']);$xpath=new \DOMXPath($dom);$rows=[];
        foreach($xpath->query('//*[local-name()="table-row"]') as $row){$values=[];foreach($xpath->query('./*[local-name()="table-cell"]',$row) as $cell){$repeat=min(max((int)($cell->getAttributeNS('urn:oasis:names:tc:opendocument:xmlns:table:1.0','number-columns-repeated')?:1),1),100);for($i=0;$i<$repeat;$i++)$values[]=trim($cell->textContent);}if(array_filter($values,fn($v)=>$v!==''))$rows[]=$values;}return $rows;
    }
    private function columnIndex(string $letters): int { $value=0;foreach(str_split($letters) as $letter)$value=$value*26+(ord($letter)-64);return max($value-1,0); }
    private function header(string $value): string { $value=mb_strtolower(trim($value));$value=strtr($value,['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c']);return preg_replace('/[^a-z0-9]+/','_',$value)??$value; }
}
