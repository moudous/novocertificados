<?php

namespace App\Http\Controllers;

use App\Models\Certificado;
use App\Models\ListaParticipante;
use App\Models\NovoCertificado;
use App\Models\Participante;
use App\Models\Template;
use App\Models\Evento;
use App\Models\Atividade;
use App\Models\Responsavel;
use App\Models\RubricaParticipante;
use App\Services\TemplateLayoutRenderer;
use App\Services\CertificadoImageGenerator;
use App\Services\PdfDigitalSigner;
use App\Services\ParticipantSpreadsheetReader;
use App\Services\ParticipantImportAnalyzer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NovoCertificadoController extends Controller
{
    private const COLUMNS = ['id','certificado_antigo_id','template_id','id','ativo','criado_em','alterado_em'];

    public function index(Request $request): View { return view('emissoes.index',['permissions'=>(array)$request->session()->get('gi_context.permissoes',[])]); }
    public function data(Request $request): JsonResponse
    {
        $query=NovoCertificado::withTrashed()->with(['certificadoAntigo.atividade','template'])->withCount('participantes')->when($request->integer('template_id'),fn(Builder $q)=>$q->where('template_id',$request->integer('template_id'))); $total=(clone $query)->count(); $search=trim((string)$request->input('search.value',''));
        if($search!=='') $query->where(fn(Builder $q):Builder=>$q->where('id','like',"%{$search}%")->orWhereHas('certificadoAntigo',fn(Builder $c):Builder=>$c->where('nome','like',"%{$search}%"))->orWhereHas('template',fn(Builder $t):Builder=>$t->where('nome','like',"%{$search}%")));
        $filtered=(clone $query)->count(); $permissions=(array)$request->session()->get('gi_context.permissoes',[]); $column=self::COLUMNS[(int)$request->input('order.0.column',0)]??'id';
        $data=$query->orderBy($column,$request->input('order.0.dir')==='asc'?'asc':'desc')->skip(max((int)$request->input('start',0),0))->take(min(max((int)$request->input('length',10),1),100))->get()->map(fn(NovoCertificado $certificado):array=>[
            'id'=>$certificado->id,'certificado_antigo'=>e($certificado->nome ?: $this->oldCertificateLabel($certificado->certificadoAntigo)),'template'=>e($this->templateLabel($certificado->template)),'participantes'=>$certificado->participantes_count,
            'ativo'=>$certificado->trashed()?'<span class="badge text-bg-danger">Excluído</span>':($certificado->ativo?'<span class="badge text-bg-success">Ativo</span>':'<span class="badge text-bg-secondary">Inativo</span>'),'criado_em'=>$certificado->criado_em?->format('d/m/Y H:i')??'—','alterado_em'=>$certificado->alterado_em?->format('d/m/Y H:i')??'—','acoes'=>view('emissoes.partials.actions',['certificado'=>$certificado,'permissions'=>$permissions])->render()]);
        return response()->json(['draw'=>(int)$request->input('draw'),'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$data]);
    }
    public function certificados(Request $request): JsonResponse
    {
        $this->authorizeEditor($request); $search=trim((string)$request->input('q','')); $items=Certificado::query()->with('atividade:id,nome')->when($search!=='',fn(Builder $q):Builder=>$q->where(fn(Builder $f):Builder=>$f->where('id','like',"%{$search}%")->orWhere('nome','like',"%{$search}%")->orWhereHas('atividade',fn(Builder $a):Builder=>$a->where('nome','like',"%{$search}%"))))->orderByDesc('id')->paginate(20,['id','nome','atividadeId'],'page',max((int)$request->input('page',1),1));
        return response()->json(['results'=>collect($items->items())->map(fn(Certificado $c):array=>['id'=>$c->id,'text'=>$this->oldCertificateLabel($c)])->values(),'pagination'=>['more'=>$items->hasMorePages()]]);
    }
    public function templates(Request $request): JsonResponse
    {
        $this->authorizeEditor($request); $search=trim((string)$request->input('q','')); $items=Template::query()->where('ativo',1)->when($search!=='',fn(Builder $q):Builder=>$q->where(fn(Builder $f):Builder=>$f->where('nome','like',"%{$search}%")->orWhere('id','like',"%{$search}%")))->orderBy('nome')->paginate(20,['id','nome','pagina','layout_pagina'],'page',max((int)$request->input('page',1),1));
        return response()->json(['results'=>collect($items->items())->map(fn(Template $t):array=>['id'=>$t->id,'text'=>$this->templateLabel($t)])->values(),'pagination'=>['more'=>$items->hasMorePages()]]);
    }
    public function activities(Request $request): JsonResponse
    {
        $this->authorizeEditor($request);
        $eventoId = $request->integer('evento_id');
        abort_if($eventoId < 1, 422, 'Selecione um evento.');
        $search = trim((string) $request->input('q', ''));
        $items = Atividade::query()->where('ativo', true)->where('eventoId', $eventoId)
            ->when($search !== '', fn (Builder $query): Builder => $query->where('nome', 'like', "%{$search}%"))
            ->orderBy('nome')->paginate(20, ['id', 'nome'], 'page', max((int) $request->input('page', 1), 1));
        return response()->json(['results' => collect($items->items())->map(fn (Atividade $atividade): array => ['id' => $atividade->id, 'text' => $atividade->nome])->values(), 'pagination' => ['more' => $items->hasMorePages()]]);
    }
    public function create(Request $request): View
    {
        $templateId = $request->integer('template_id') ?: null;
        $certificado = new NovoCertificado([
            'template_id' => $templateId,
            'nome' => $templateId ? $this->suggestedEmissionName($templateId) : null,
        ]);
        if ($templateId) $certificado->setRelation('template', Template::find($templateId));
        return view('emissoes.form', $this->formData($certificado));
    }
    public function store(Request $request): RedirectResponse { $certificado=NovoCertificado::query()->create($this->validated($request)); return redirect()->route('emissoes.show',$certificado)->with('status','Novo certificado cadastrado com sucesso.'); }
    public function show(NovoCertificado $certificado): View { $certificado->load(['certificadoAntigo.atividade','template'])->loadCount('participantes'); return view('emissoes.show',compact('certificado')); }
    public function edit(NovoCertificado $certificado): View { $certificado->load(['certificadoAntigo.atividade','template','atividade']); return view('emissoes.form',$this->formData($certificado)); }
    public function update(Request $request,NovoCertificado $certificado): RedirectResponse { $certificado->update($this->validated($request)); return redirect()->route('emissoes.show',$certificado)->with('status','Novo certificado atualizado com sucesso.'); }
    public function toggleStatus(NovoCertificado $certificado): RedirectResponse { $certificado->update(['ativo'=>!$certificado->ativo]); return redirect()->route('emissoes.index')->with('status','Status atualizado com sucesso.'); }
    public function destroy(NovoCertificado $certificado): RedirectResponse { $certificado->delete(); return redirect()->route('emissoes.index')->with('status','Certificado excluído com sucesso.'); }
    public function forceDestroy(int $certificado): RedirectResponse
    {
        $model = NovoCertificado::withTrashed()->withCount('participantes')->findOrFail($certificado);

        if ($model->participantes_count > 0) {
            return redirect()->route('emissoes.index')->withErrors([
                'certificado' => 'Não é possível excluir definitivamente uma emissão que possui participantes.',
            ]);
        }

        $model->forceDelete();

        return redirect()->route('emissoes.index')->with('status', 'Emissão excluída definitivamente.');
    }
    public function participants(NovoCertificado $certificado,Request $request,ParticipantImportAnalyzer $analyzer): View
    {
        $certificado->load('template');
        $templateFields=$certificado->template?->usedTemplateFields()??[];
        $stored=(array)$request->session()->get($this->importSessionKey($certificado),[]);
        $importAnalysis=isset($stored['rows'])?$analyzer->analyze($certificado,(array)$stored['rows']):null;
        return view('emissoes.participantes',['certificado'=>$certificado,'items'=>$certificado->participantes()->with('participante')->orderByDesc('id')->get(),'permissions'=>(array)$request->session()->get('gi_context.permissoes',[]),'importAnalysis'=>$importAnalysis,'templateFields'=>$templateFields]);
    }
    public function participantOptions(NovoCertificado $certificado,Request $request): JsonResponse
    {
        $ownOnly=$this->authorizeParticipantInsertion($request); $search=trim((string)$request->input('q',''));
        $items=Participante::query()->when($ownOnly,fn(Builder $q):Builder=>$q->where('criado_por',$this->sessionUserId($request)))->whereNotIn('id',$certificado->participantes()->select('participante_id'))->when($search!=='',fn(Builder $q):Builder=>$q->where(fn(Builder $f):Builder=>$f->where('nome','like',"%{$search}%")->orWhere('email','like',"%{$search}%")))->orderBy('nome')->paginate(20,['id','nome','email'],'page',max((int)$request->input('page',1),1));
        return response()->json(['results'=>collect($items->items())->map(fn(Participante $p):array=>['id'=>$p->id,'text'=>$p->nome.($p->email?' · '.$p->email:'')])->values(),'pagination'=>['more'=>$items->hasMorePages()]]);
    }
    public function addParticipants(NovoCertificado $certificado,Request $request): RedirectResponse
    {
        $ownOnly=$this->authorizeParticipantInsertion($request);$userId=$this->sessionUserId($request);$data=$request->validate(['participantes'=>['required','array','min:1'],'participantes.*'=>['integer','distinct',Rule::exists('participantes','id')->where(fn($q)=>$q->whereNull('excluido_em')->when($ownOnly,fn($q)=>$q->where('criado_por',$userId)))]]);$custom=$this->validatedTemplateValues($request,$certificado);DB::transaction(function()use($certificado,$data,$userId,$custom){foreach($data['participantes'] as $id)$certificado->participantes()->firstOrCreate(['participante_id'=>$id],['adicionado_por'=>$userId,'dados_personalizados'=>$custom]);$certificado->update(['lista_participantes_id'=>$certificado->participantes()->min('id')]);}); return back()->with('status','Participantes adicionados com sucesso.');
    }
    public function analyzeParticipantSpreadsheet(NovoCertificado $certificado,Request $request,ParticipantSpreadsheetReader $reader): RedirectResponse
    {
        $this->authorizeParticipantInsertion($request);
        $data=$request->validate(['planilha'=>['required','file','max:10240','extensions:csv,xls,xlsx,ods,odt']]);
        $certificado->load('template');
        $rows=$reader->read($data['planilha'],collect($certificado->template?->usedTemplateFields()??[])->pluck('nome')->all());
        $request->session()->put($this->importSessionKey($certificado),['rows'=>$rows,'filename'=>$data['planilha']->getClientOriginalName()]);
        return back()->with('status','Planilha analisada. Revise as decisões antes de importar.')->with('participant_add_mode','spreadsheet');
    }
    public function importParticipantSpreadsheet(NovoCertificado $certificado,Request $request,ParticipantImportAnalyzer $analyzer): RedirectResponse
    {
        $ownOnly=$this->authorizeParticipantInsertion($request);
        $stored=(array)$request->session()->get($this->importSessionKey($certificado),[]);$original=(array)($stored['rows']??[]);if(!$original)return back()->withErrors(['planilha'=>'A análise expirou. Envie a planilha novamente.'])->with('participant_add_mode','spreadsheet');
        $input=(array)$request->input('rows',[]);$adjusted=[];foreach($original as $index=>$row){$row['nome']=trim((string)data_get($input,$index.'.nome',$row['nome']??''));$row['email']=trim((string)data_get($input,$index.'.email',$row['email']??''));$adjusted[]=$row;}
        $request->session()->put($this->importSessionKey($certificado),['rows'=>$adjusted,'filename'=>$stored['filename']??'planilha']);
        $analysis=$analyzer->analyze($certificado,$adjusted);$userId=$this->sessionUserId($request);$created=0;$recovered=0;$skipped=0;$fieldNames=collect($certificado->template?->usedTemplateFields()??[])->pluck('nome')->all();
        DB::transaction(function()use($certificado,$analysis,$input,$userId,$ownOnly,$fieldNames,&$created,&$recovered,&$skipped){foreach($analysis['rows'] as $row){$action=(string)data_get($input,$row['index'].'.action','');if(in_array($row['kind'],['linked','repeated'],true)||$action==='skip'){$skipped++;continue;}if($row['kind']==='incomplete')throw ValidationException::withMessages(['planilha'=>'Preencha nome e e-mail ou escolha “Não importar” na linha '.$row['line'].'.']);$participantId=null;if($row['kind']==='recovered'){$participantId=$row['existing_id'];$recovered++;}elseif($row['kind']==='conflict'){if(!in_array($action,['use_existing','create_new'],true))throw ValidationException::withMessages(['planilha'=>'Selecione uma ação para a linha '.$row['line'].'.']);if($action==='use_existing'){$participantId=$row['existing_id'];$recovered++;}}if($participantId&&$ownOnly&&!Participante::query()->whereKey($participantId)->where('criado_por',$userId)->exists())throw ValidationException::withMessages(['planilha'=>'Você só pode adicionar participantes criados por você.']);if(!$participantId){$participant=Participante::query()->create(['nome'=>$row['nome'],'email'=>$row['email'],'sexo'=>$this->normalizeSex($row['sexo']),'cpf'=>strlen($row['cpf'])===11?$row['cpf']:null,'grupo'=>mb_substr($row['grupo'],0,1)?:null,'ativo'=>true,'criado_por'=>$userId]);$participantId=$participant->id;$created++;}$custom=collect($fieldNames)->mapWithKeys(fn($name)=>[$name=>$row[$name]??null])->all();$certificado->participantes()->firstOrCreate(['participante_id'=>$participantId],['adicionado_por'=>$userId,'dados_personalizados'=>$custom]);}$certificado->update(['lista_participantes_id'=>$certificado->participantes()->min('id')]);});
        $request->session()->forget($this->importSessionKey($certificado));
        return back()->with('status',"Importação concluída: {$created} novo(s), {$recovered} recuperado(s) e {$skipped} não importado(s).");
    }
    public function removeParticipant(NovoCertificado $certificado,ListaParticipante $item): RedirectResponse { abort_unless($item->novo_certificado_id===$certificado->id,404); $item->delete(); $certificado->update(['lista_participantes_id'=>$certificado->participantes()->min('id')]); return back()->with('status','Participante removido com sucesso.'); }
    public function updateParticipantFields(NovoCertificado $certificado, ListaParticipante $item, Request $request): RedirectResponse
    {
        $this->authorizeParticipantInsertion($request); abort_unless($item->novo_certificado_id===$certificado->id,404);
        $item->update(['dados_personalizados'=>$this->validatedTemplateValues($request,$certificado)]);
        return back()->with('status','Campos dinâmicos atualizados.');
    }
    public function exampleParticipantSpreadsheet(NovoCertificado $certificado): StreamedResponse
    {
        $certificado->load('template'); $layout=$certificado->template?->elementos_layout??[]; $system=[];
        foreach($layout as $element){if(($element['source_type']??null)==='dynamic'&&!str_starts_with((string)($element['source_key']??''),'template.'))$system[]=(string)$element['source_key'];preg_match_all('/\{\{\s*((?!template\.)[a-z_]+\.[a-z0-9_]+)\s*\}\}/i',(string)($element['content']??''),$matches);$system=array_merge($system,$matches[1]??[]);}
        $headers=array_values(array_unique(array_merge(['nome','email'],array_map(fn($key)=>str_replace('.','_',$key),$system),collect($certificado->template?->usedTemplateFields()??[])->pluck('nome')->all())));
        $spreadsheet=new Spreadsheet();$sheet=$spreadsheet->getActiveSheet();$sheet->fromArray($headers,null,'A1');$sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFont()->setBold(true);
        return response()->streamDownload(fn()=>(new Xlsx($spreadsheet))->save('php://output'),'participantes-exemplo-emissao-'.$certificado->id.'.xlsx',['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
    public function generate(NovoCertificado $certificado, TemplateLayoutRenderer $renderer, PdfDigitalSigner $signer): RedirectResponse
    {
        $certificado->load(['template.imagemBiblioteca','template.certificadoA1','atividade.evento','responsavel.participante','rubrica','participantes.participante']);
        abort_unless($certificado->template,422,'Selecione um template antes de gerar.');
        foreach($certificado->participantes as $item){try{$this->generateItem($certificado,$item,$renderer,$signer);}catch(\Throwable $e){report($e);$item->update(['erro_geracao'=>Str::limit($e->getMessage(),1000),'gerado_em'=>null]);}}
        return back()->with('status','Geração concluída. Consulte o resultado de cada participante.');
    }
    public function generateParticipant(Request $request, NovoCertificado $certificado, ListaParticipante $item, TemplateLayoutRenderer $renderer, PdfDigitalSigner $signer): RedirectResponse|JsonResponse
    {
        abort_unless($item->novo_certificado_id === $certificado->id, 404);
        $certificado->load(['template.imagemBiblioteca','template.certificadoA1','atividade.evento','responsavel.participante','rubrica']);
        $item->load('participante');
        abort_unless($certificado->template, 422, 'Selecione um template antes de gerar.');
        try {
            $this->generateItem($certificado, $item, $renderer, $signer);
        } catch (\Throwable $exception) {
            report($exception);
            $item->update(['erro_geracao'=>Str::limit($exception->getMessage(),1000),'gerado_em'=>null]);
            if($request->expectsJson())return response()->json(['message'=>'Não foi possível gerar o PDF deste participante.'],422);
            return back()->withErrors(['pdf' => 'Não foi possível gerar o PDF deste participante.']);
        }
        if($request->expectsJson())return response()->json(['html'=>'<a target="_blank" rel="noopener noreferrer" href="'.e(route('emissoes.participantes.pdf',[$certificado,$item])).'" class="btn btn-sm btn-outline-danger listagem-acao" title="Abrir PDF" aria-label="Abrir PDF"><i class="bi bi-file-earmark-pdf-fill"></i></a>']);
        return back()->with('status', 'PDF do participante gerado com sucesso.');
    }
    public function generateParticipantImage(Request $request, NovoCertificado $certificado, ListaParticipante $item, CertificadoImageGenerator $generator): RedirectResponse|JsonResponse
    {
        abort_unless($item->novo_certificado_id === $certificado->id, 404);
        try { $generator->generate($item); }
        catch (\Throwable $exception) { report($exception); if($request->expectsJson())return response()->json(['message'=>'Não foi possível gerar a imagem deste participante.'],422); return back()->withErrors(['img'=>'Não foi possível gerar a imagem deste participante.']); }
        $item->refresh();
        if($request->expectsJson())return response()->json(['html'=>'<a target="_blank" rel="noopener noreferrer" href="'.e(route('certificadosnovos.public.image',$item->codigo_img)).'" class="btn btn-sm btn-outline-primary listagem-acao" title="Abrir imagem" aria-label="Abrir imagem"><i class="bi bi-image-fill"></i></a>']);
        return back()->with('status','Imagem do participante gerada com sucesso.');
    }
    public function pdf(NovoCertificado $certificado, ListaParticipante $item): BinaryFileResponse { abort_unless($item->novo_certificado_id===$certificado->id&&filled($item->arquivo_pdf),404);$path=public_path('certificado/emitidos/'.$item->arquivo_pdf);abort_unless(is_file($path),404);return response()->file($path); }
    private function generateItem(NovoCertificado $certificado, ListaParticipante $item, TemplateLayoutRenderer $renderer, PdfDigitalSigner $signer): void
    {
        $directory=public_path('certificado/emitidos'); File::ensureDirectoryExists($directory);
        $code=$item->codigo?:strtoupper(Str::random(16)); $responsavel=$certificado->responsavel;
        $rubrica=$certificado->rubrica?:$responsavel?->participante?->rubricas()->where('ativo',true)->first();
        $context=['participante'=>['nome'=>$item->participante?->nome,'email'=>$item->participante?->email,'cpf'=>$item->participante?->cpf],'evento'=>['nome'=>$certificado->atividade?->evento?->nome,'descricao'=>$certificado->atividade?->evento?->descricao],'atividade'=>['nome'=>$certificado->atividade?->nome,'carga_horaria'=>data_get($certificado->campos_personalizados,'carga_horaria','')],'responsavel'=>['nome'=>$responsavel?->participante?->nome,'cargo'=>$responsavel?->cargo,'titulacao'=>$responsavel?->titulacao,'rubrica_path'=>$renderer->rubricaPath($rubrica)],'emissao'=>['nome'=>$certificado->nome,'data'=>($certificado->data_emissao?:now())->format('d/m/Y')],'certificado'=>['codigo'=>$code],'template'=>$item->dados_personalizados??[],'link_validacao'=>route('certificadosnovos.public.pdf',$code)];
        $elements=collect($renderer->elements($certificado->template->elementos_layout??[],$context)); $width=max($certificado->template->largura,1); $height=max($certificado->template->altura,1); $background=$renderer->background($certificado->template); $fonts=collect($renderer->fonts());
        $pdf=Pdf::loadView('templates.preview-pdf',['template'=>$certificado->template,'elements'=>$elements,'width'=>$width,'height'=>$height,'background'=>$background,'fonts'=>$fonts])->setPaper([0,0,$width*2.834645669,$height*2.834645669]);
        $name='certificado-'.$item->id.'-'.$code.'.pdf'; File::put($directory.'/'.$name,$signer->output($pdf,$certificado->template->certificadoA1,'Emissão de certificado digital'));
        $item->update(['codigo'=>$code,'arquivo_pdf'=>$name,'snapshot_dados'=>$context,'snapshot_template'=>$certificado->template->elementos_layout,'gerado_em'=>now(),'erro_geracao'=>null]);
    }
    private function validated(Request $request): array
    {
        $eventoId = $request->integer('evento_id');
        return $request->validate([
            'nome'=>['required','string','max:150'],
            'certificado_antigo_id'=>['nullable','integer',Rule::exists('certificados','id')->whereNull('apagado_em')],
            'template_id'=>['required','integer',Rule::exists('templates','id')->whereNull('apagado_em')],
            'evento_id'=>['nullable','required_with:atividade_id','integer',Rule::exists('eventos','id')->where(fn ($query) => $query->where('ativo', true)->whereNull('apagado_em'))],
            'atividade_id'=>['nullable','integer',Rule::prohibitedIf($eventoId < 1),Rule::exists('atividades','id')->where(fn ($query) => $query->where('eventoId', $eventoId)->where('ativo', true)->whereNull('apagado_em'))],
            'responsavel_id'=>['nullable','integer',Rule::exists('responsaveis','id')->where(fn($q)=>$q->where('ativo',true)->whereNull('apagado_em'))],
            'rubrica_id'=>['nullable','integer',Rule::exists('rubricas_participantes','id')->where(fn($q)=>$q->where('ativo',true)->whereNull('apagado_em'))],
            'data_emissao'=>['required','date'],'campos_personalizados'=>['nullable','array'],'ativo'=>['required','boolean'],
        ], [
            'evento_id.required_with' => 'Selecione um evento antes da atividade.',
            'atividade_id.prohibited' => 'Não é permitido escolher uma atividade sem evento.',
            'atividade_id.exists' => 'A atividade selecionada não pertence ao evento informado.',
        ]);
    }
    private function formData(NovoCertificado $certificado): array { return compact('certificado')+['events'=>Evento::where('ativo',true)->orderBy('nome')->get()]; }
    private function suggestedEmissionName(int $templateId): string
    {
        $query = NovoCertificado::withTrashed()->where('template_id', $templateId);
        $number = (clone $query)->count() + 1;
        while ((clone $query)->where('nome', 'Emissão #'.$number)->exists()) $number++;
        return 'Emissão #'.$number;
    }
    private function authorizeEditor(Request $request): void { $p=(array)$request->session()->get('gi_context.permissoes',[]); abort_unless(in_array('emissoes.criar',$p,true)||in_array('emissoes.editar',$p,true),403); }
    private function authorizeParticipantInsertion(Request $request): bool { $p=(array)$request->session()->get('gi_context.permissoes',[]);if(in_array('emissoes.inserir_participantes',$p,true))return false;abort_unless(in_array('emissoes.inserir_participantes_proprios',$p,true),403);return true; }
    private function importSessionKey(NovoCertificado $certificado): string { return 'emissoes.'.$certificado->id.'.participant_import'; }
    private function sessionUserId(Request $request): ?int { $id=(int)$request->session()->get('gi_context.usuario.id',0);return $id>0?$id:null; }
    private function normalizeSex(string $value): ?string { $value=mb_strtoupper(trim($value));return in_array($value,['M','F'],true)?$value:null; }
    private function validatedTemplateValues(Request $request, NovoCertificado $certificado): array
    {
        $certificado->loadMissing('template');$fields=$certificado->template?->usedTemplateFields()??[];$rules=[];
        foreach($fields as $field){$name=$field['nome'];$rule=['required'];$rule[]=$field['tipo']==='number'?'numeric':($field['tipo']==='data'?'date':'string');if($field['tipo']==='lista')$rule[]=Rule::in($field['opcoes']??[]);$rules['campos.'.$name]=$rule;}
        return (array)data_get($request->validate($rules),'campos',[]);
    }
    private function oldCertificateLabel(?Certificado $c): string { return $c?"#{$c->id} - ".($c->nome?:'Sem nome').' - '.($c->atividade?->nome?:'Sem atividade'):'—'; }
    private function templateLabel(?Template $t): string { return $t?"#{$t->id} - ".($t->nome?:'Sem nome')." - (".($t->pagina?:'—').' - '.($t->layout_pagina?:'—').')':'—'; }
}
