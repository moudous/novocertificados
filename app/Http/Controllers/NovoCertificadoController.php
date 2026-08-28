<?php

namespace App\Http\Controllers;

use App\Models\Certificado;
use App\Models\ListaParticipante;
use App\Models\NovoCertificado;
use App\Models\Participante;
use App\Models\Template;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NovoCertificadoController extends Controller
{
    private const COLUMNS = ['id','certificado_antigo_id','template_id','id','ativo','criado_em','alterado_em'];

    public function index(Request $request): View { return view('certificadosnovos.index',['permissions'=>(array)$request->session()->get('gi_context.permissoes',[])]); }
    public function data(Request $request): JsonResponse
    {
        $query=NovoCertificado::withTrashed()->with(['certificadoAntigo.atividade','template'])->withCount('participantes'); $total=(clone $query)->count(); $search=trim((string)$request->input('search.value',''));
        if($search!=='') $query->where(fn(Builder $q):Builder=>$q->where('id','like',"%{$search}%")->orWhereHas('certificadoAntigo',fn(Builder $c):Builder=>$c->where('nome','like',"%{$search}%"))->orWhereHas('template',fn(Builder $t):Builder=>$t->where('nome','like',"%{$search}%")));
        $filtered=(clone $query)->count(); $permissions=(array)$request->session()->get('gi_context.permissoes',[]); $column=self::COLUMNS[(int)$request->input('order.0.column',0)]??'id';
        $data=$query->orderBy($column,$request->input('order.0.dir')==='asc'?'asc':'desc')->skip(max((int)$request->input('start',0),0))->take(min(max((int)$request->input('length',10),1),100))->get()->map(fn(NovoCertificado $certificado):array=>[
            'id'=>$certificado->id,'certificado_antigo'=>e($this->oldCertificateLabel($certificado->certificadoAntigo)),'template'=>e($this->templateLabel($certificado->template)),'participantes'=>$certificado->participantes_count,
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
    public function create(): View { return view('certificadosnovos.form',['certificado'=>new NovoCertificado()]); }
    public function store(Request $request): RedirectResponse { $certificado=NovoCertificado::query()->create($this->validated($request)); return redirect()->route('certificadosnovos.show',$certificado)->with('status','Novo certificado cadastrado com sucesso.'); }
    public function show(NovoCertificado $certificado): View { $certificado->load(['certificadoAntigo.atividade','template'])->loadCount('participantes'); return view('certificadosnovos.show',compact('certificado')); }
    public function edit(NovoCertificado $certificado): View { $certificado->load(['certificadoAntigo.atividade','template']); return view('certificadosnovos.form',compact('certificado')); }
    public function update(Request $request,NovoCertificado $certificado): RedirectResponse { $certificado->update($this->validated($request)); return redirect()->route('certificadosnovos.show',$certificado)->with('status','Novo certificado atualizado com sucesso.'); }
    public function toggleStatus(NovoCertificado $certificado): RedirectResponse { $certificado->update(['ativo'=>!$certificado->ativo]); return redirect()->route('certificadosnovos.index')->with('status','Status atualizado com sucesso.'); }
    public function destroy(NovoCertificado $certificado): RedirectResponse { $certificado->delete(); return redirect()->route('certificadosnovos.index')->with('status','Certificado excluído com sucesso.'); }
    public function forceDestroy(int $certificado): RedirectResponse { $model=NovoCertificado::withTrashed()->findOrFail($certificado); DB::transaction(function()use($model){$model->participantes()->delete();$model->forceDelete();}); return redirect()->route('certificadosnovos.index')->with('status','Certificado excluído definitivamente.'); }
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
    private function validated(Request $request): array { return $request->validate(['certificado_antigo_id'=>['nullable','integer',Rule::exists('certificados','id')->whereNull('apagado_em')],'template_id'=>['nullable','integer',Rule::exists('templates','id')->whereNull('apagado_em')],'ativo'=>['required','boolean']]); }
    private function authorizeEditor(Request $request): void { $p=(array)$request->session()->get('gi_context.permissoes',[]); abort_unless(in_array('novos_certificados.criar',$p,true)||in_array('novos_certificados.editar',$p,true),403); }
    private function oldCertificateLabel(?Certificado $c): string { return $c?"#{$c->id} - ".($c->nome?:'Sem nome').' - '.($c->atividade?->nome?:'Sem atividade'):'—'; }
    private function templateLabel(?Template $t): string { return $t?"#{$t->id} - ".($t->nome?:'Sem nome')." - (".($t->pagina?:'—').' - '.($t->layout_pagina?:'—').')':'—'; }
}
