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
use Illuminate\View\View;

class NovoCertificadoController extends Controller
{
    private const COLUMNS = ['id','certificado_antigo_id','template_id','id','ativo','criado_em','alterado_em'];

    public function index(Request $request): View { return view('certificadosnovos.index',['permissions'=>(array)$request->session()->get('gi_context.permissoes',[])]); }
    public function data(Request $request): JsonResponse
    {
        $query=NovoCertificado::withTrashed()->with(['certificadoAntigo.atividade','template'])->withCount('participantes')->when($request->integer('template_id'),fn(Builder $q)=>$q->where('template_id',$request->integer('template_id'))); $total=(clone $query)->count(); $search=trim((string)$request->input('search.value',''));
        if($search!=='') $query->where(fn(Builder $q):Builder=>$q->where('id','like',"%{$search}%")->orWhereHas('certificadoAntigo',fn(Builder $c):Builder=>$c->where('nome','like',"%{$search}%"))->orWhereHas('template',fn(Builder $t):Builder=>$t->where('nome','like',"%{$search}%")));
        $filtered=(clone $query)->count(); $permissions=(array)$request->session()->get('gi_context.permissoes',[]); $column=self::COLUMNS[(int)$request->input('order.0.column',0)]??'id';
        $data=$query->orderBy($column,$request->input('order.0.dir')==='asc'?'asc':'desc')->skip(max((int)$request->input('start',0),0))->take(min(max((int)$request->input('length',10),1),100))->get()->map(fn(NovoCertificado $certificado):array=>[
            'id'=>$certificado->id,'certificado_antigo'=>e($certificado->nome ?: $this->oldCertificateLabel($certificado->certificadoAntigo)),'template'=>e($this->templateLabel($certificado->template)),'participantes'=>$certificado->participantes_count,
            'ativo'=>$certificado->trashed()?'<span class="badge text-bg-danger">Excluído</span>':($certificado->ativo?'<span class="badge text-bg-success">Ativo</span>':'<span class="badge text-bg-secondary">Inativo</span>'),'criado_em'=>$certificado->criado_em?->format('d/m/Y H:i')??'—','alterado_em'=>$certificado->alterado_em?->format('d/m/Y H:i')??'—','acoes'=>view('certificadosnovos.partials.actions',['certificado'=>$certificado,'permissions'=>$permissions])->render()]);
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
    public function create(Request $request): View { $certificado=new NovoCertificado(['template_id'=>$request->integer('template_id')?:null]);if($certificado->template_id)$certificado->setRelation('template',Template::find($certificado->template_id));return view('certificadosnovos.form',$this->formData($certificado)); }
    public function store(Request $request): RedirectResponse { $certificado=NovoCertificado::query()->create($this->validated($request)); return redirect()->route('certificadosnovos.show',$certificado)->with('status','Novo certificado cadastrado com sucesso.'); }
    public function show(NovoCertificado $certificado): View { $certificado->load(['certificadoAntigo.atividade','template'])->loadCount('participantes'); return view('certificadosnovos.show',compact('certificado')); }
    public function edit(NovoCertificado $certificado): View { $certificado->load(['certificadoAntigo.atividade','template']); return view('certificadosnovos.form',$this->formData($certificado)); }
    public function update(Request $request,NovoCertificado $certificado): RedirectResponse { $certificado->update($this->validated($request)); return redirect()->route('certificadosnovos.show',$certificado)->with('status','Novo certificado atualizado com sucesso.'); }
    public function toggleStatus(NovoCertificado $certificado): RedirectResponse { $certificado->update(['ativo'=>!$certificado->ativo]); return redirect()->route('certificadosnovos.index')->with('status','Status atualizado com sucesso.'); }
    public function destroy(NovoCertificado $certificado): RedirectResponse { $certificado->delete(); return redirect()->route('certificadosnovos.index')->with('status','Certificado excluído com sucesso.'); }
    public function forceDestroy(int $certificado): RedirectResponse
    {
        $model = NovoCertificado::withTrashed()->withCount('participantes')->findOrFail($certificado);

        if ($model->participantes_count > 0) {
            return redirect()->route('certificadosnovos.index')->withErrors([
                'certificado' => 'Não é possível excluir definitivamente uma emissão que possui participantes.',
            ]);
        }

        $model->forceDelete();

        return redirect()->route('certificadosnovos.index')->with('status', 'Emissão excluída definitivamente.');
    }
    public function participants(NovoCertificado $certificado,Request $request): View { return view('certificadosnovos.participantes',['certificado'=>$certificado,'items'=>$certificado->participantes()->with('participante')->orderByDesc('id')->get(),'permissions'=>(array)$request->session()->get('gi_context.permissoes',[])]); }
    public function participantOptions(NovoCertificado $certificado,Request $request): JsonResponse
    {
        $permissions=(array)$request->session()->get('gi_context.permissoes',[]); abort_unless(in_array('novos_certificados.inserir_participantes',$permissions,true),403); $search=trim((string)$request->input('q',''));
        $items=Participante::query()->whereNotIn('id',$certificado->participantes()->select('participante_id'))->when($search!=='',fn(Builder $q):Builder=>$q->where(fn(Builder $f):Builder=>$f->where('nome','like',"%{$search}%")->orWhere('email','like',"%{$search}%")))->orderBy('nome')->paginate(20,['id','nome','email'],'page',max((int)$request->input('page',1),1));
        return response()->json(['results'=>collect($items->items())->map(fn(Participante $p):array=>['id'=>$p->id,'text'=>$p->nome.($p->email?' · '.$p->email:'')])->values(),'pagination'=>['more'=>$items->hasMorePages()]]);
    }
    public function addParticipants(NovoCertificado $certificado,Request $request): RedirectResponse
    {
        $data=$request->validate(['participantes'=>['required','array','min:1'],'participantes.*'=>['integer','distinct',Rule::exists('participantes','id')->whereNull('excluido_em')]]); DB::transaction(function()use($certificado,$data){foreach($data['participantes'] as $id)$certificado->participantes()->firstOrCreate(['participante_id'=>$id]);$certificado->update(['lista_participantes_id'=>$certificado->participantes()->min('id')]);}); return back()->with('status','Participantes adicionados com sucesso.');
    }
    public function removeParticipant(NovoCertificado $certificado,ListaParticipante $item): RedirectResponse { abort_unless($item->novo_certificado_id===$certificado->id,404); $item->delete(); $certificado->update(['lista_participantes_id'=>$certificado->participantes()->min('id')]); return back()->with('status','Participante removido com sucesso.'); }
    public function generate(NovoCertificado $certificado, TemplateLayoutRenderer $renderer): RedirectResponse
    {
        $certificado->load(['template.imagemBiblioteca','atividade.evento','responsavel.participante','rubrica','participantes.participante']);
        abort_unless($certificado->template,422,'Selecione um template antes de gerar.');
        $directory=public_path('certificado/emitidos');File::ensureDirectoryExists($directory);
        foreach($certificado->participantes as $item){try{$code=$item->codigo?:strtoupper(Str::random(16));$r=$certificado->responsavel;$rubrica=$certificado->rubrica?:$r?->participante?->rubricas()->where('ativo',true)->first();$context=['participante'=>['nome'=>$item->participante?->nome,'email'=>$item->participante?->email,'cpf'=>$item->participante?->cpf],'evento'=>['nome'=>$certificado->atividade?->evento?->nome,'descricao'=>$certificado->atividade?->evento?->descricao],'atividade'=>['nome'=>$certificado->atividade?->nome,'carga_horaria'=>data_get($certificado->campos_personalizados,'carga_horaria','')],'responsavel'=>['nome'=>$r?->participante?->nome,'cargo'=>$r?->cargo,'titulacao'=>$r?->titulacao,'rubrica_path'=>$renderer->rubricaPath($rubrica)],'emissao'=>['nome'=>$certificado->nome,'data'=>($certificado->data_emissao?:now())->format('d/m/Y')],'certificado'=>['codigo'=>$code]];$elements=collect($renderer->elements($certificado->template->elementos_layout??[],$context));$width=max($certificado->template->largura,1);$height=max($certificado->template->altura,1);$background=$renderer->background($certificado->template);$fonts=collect($renderer->fonts());$pdf=Pdf::loadView('templates.preview-pdf',['template'=>$certificado->template,'elements'=>$elements,'width'=>$width,'height'=>$height,'background'=>$background,'fonts'=>$fonts])->setPaper([0,0,$width*2.834645669,$height*2.834645669]);$name='certificado-'.$item->id.'-'.$code.'.pdf';File::put($directory.'/'.$name,$pdf->output());$item->update(['codigo'=>$code,'arquivo_pdf'=>$name,'snapshot_dados'=>$context,'snapshot_template'=>$certificado->template->elementos_layout,'gerado_em'=>now(),'erro_geracao'=>null]);}catch(\Throwable $e){report($e);$item->update(['erro_geracao'=>Str::limit($e->getMessage(),1000),'gerado_em'=>null]);}}
        return back()->with('status','Geração concluída. Consulte o resultado de cada participante.');
    }
    public function pdf(NovoCertificado $certificado, ListaParticipante $item): BinaryFileResponse { abort_unless($item->novo_certificado_id===$certificado->id&&filled($item->arquivo_pdf),404);$path=public_path('certificado/emitidos/'.$item->arquivo_pdf);abort_unless(is_file($path),404);return response()->file($path); }
    private function validated(Request $request): array { return $request->validate(['nome'=>['required','string','max:150'],'certificado_antigo_id'=>['nullable','integer',Rule::exists('certificados','id')->whereNull('apagado_em')],'template_id'=>['required','integer',Rule::exists('templates','id')->whereNull('apagado_em')],'evento_id'=>['nullable','integer',Rule::exists('eventos','id')->whereNull('apagado_em')],'atividade_id'=>['nullable','integer',Rule::exists('atividades','id')->whereNull('apagado_em')],'responsavel_id'=>['nullable','integer',Rule::exists('responsaveis','id')->where(fn($q)=>$q->where('ativo',true)->whereNull('apagado_em'))],'rubrica_id'=>['nullable','integer',Rule::exists('rubricas_participantes','id')->where(fn($q)=>$q->where('ativo',true)->whereNull('apagado_em'))],'data_emissao'=>['required','date'],'campos_personalizados'=>['nullable','array'],'ativo'=>['required','boolean']]); }
    private function formData(NovoCertificado $certificado): array { return compact('certificado')+['events'=>Evento::where('ativo',true)->orderBy('nome')->get(),'activities'=>Atividade::where('ativo',true)->orderBy('nome')->get(),'responsibles'=>Responsavel::with('participante')->where('ativo',true)->orderBy('id')->get(),'signatures'=>RubricaParticipante::with('participante')->where('ativo',true)->whereHas('participante.responsavel',fn(Builder $q)=>$q->where('ativo',true))->get()]; }
    private function authorizeEditor(Request $request): void { $p=(array)$request->session()->get('gi_context.permissoes',[]); abort_unless(in_array('novos_certificados.criar',$p,true)||in_array('novos_certificados.editar',$p,true),403); }
    private function oldCertificateLabel(?Certificado $c): string { return $c?"#{$c->id} - ".($c->nome?:'Sem nome').' - '.($c->atividade?->nome?:'Sem atividade'):'—'; }
    private function templateLabel(?Template $t): string { return $t?"#{$t->id} - ".($t->nome?:'Sem nome')." - (".($t->pagina?:'—').' - '.($t->layout_pagina?:'—').')':'—'; }
}
