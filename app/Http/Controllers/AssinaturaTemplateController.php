<?php

namespace App\Http\Controllers;

use App\Models\AssinaturaTemplate;
use App\Models\Participante;
use App\Models\Template;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssinaturaTemplateController extends Controller
{
    private const COLUMNS = ['id','participante_id','template_id','titulacao','rubrica_id','ativo','criado_em','alterado_em'];

    public function index(Request $request): View { return view('assinaturas_template.index', ['permissions'=>(array)$request->session()->get('gi_context.permissoes',[])]); }
    public function data(Request $request): JsonResponse
    {
        $query=AssinaturaTemplate::withTrashed()->with(['participante','template']); $total=(clone $query)->count(); $search=trim((string)$request->input('search.value',''));
        if($search!=='') $query->where(fn(Builder $q):Builder=>$q->where('titulacao','like',"%{$search}%")->orWhere('rubrica_id','like',"%{$search}%")->orWhereHas('participante',fn(Builder $p):Builder=>$p->where('nome','like',"%{$search}%"))->orWhereHas('template',fn(Builder $t):Builder=>$t->where('nome','like',"%{$search}%")));
        $filtered=(clone $query)->count(); $column=self::COLUMNS[(int)$request->input('order.0.column',0)]??'id'; $permissions=(array)$request->session()->get('gi_context.permissoes',[]);
        $data=$query->orderBy($column,$request->input('order.0.dir')==='asc'?'asc':'desc')->skip(max((int)$request->input('start',0),0))->take(min(max((int)$request->input('length',10),1),100))->get()->map(fn(AssinaturaTemplate $assinatura):array=>[
            'id'=>$assinatura->id,'participante'=>e($assinatura->participante?->nome?:'—'),'template'=>e($this->templateLabel($assinatura->template)),'titulacao'=>e($assinatura->titulacao?:'—'),'rubrica_id'=>$assinatura->rubrica_id??'—',
            'ativo'=>$assinatura->trashed()?'<span class="badge text-bg-danger">Excluída</span>':($assinatura->ativo?'<span class="badge text-bg-success">Ativa</span>':'<span class="badge text-bg-secondary">Inativa</span>'),'criado_em'=>$assinatura->criado_em?->format('d/m/Y H:i')??'—','alterado_em'=>$assinatura->alterado_em?->format('d/m/Y H:i')??'—','acoes'=>view('assinaturas_template.partials.actions',['assinatura'=>$assinatura,'permissions'=>$permissions])->render()]);
        return response()->json(['draw'=>(int)$request->input('draw'),'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$data]);
    }
    public function participantes(Request $request): JsonResponse
    {
        $this->authorizeSelector($request); $search=trim((string)$request->input('q','')); $items=Participante::query()->when($search!=='',fn(Builder $q):Builder=>$q->where(fn(Builder $f):Builder=>$f->where('nome','like',"%{$search}%")->orWhere('email','like',"%{$search}%")))->orderBy('nome')->paginate(20,['id','nome','email'],'page',max((int)$request->input('page',1),1));
        return response()->json(['results'=>collect($items->items())->map(fn(Participante $p):array=>['id'=>$p->id,'text'=>$p->nome.($p->email?' · '.$p->email:'')])->values(),'pagination'=>['more'=>$items->hasMorePages()]]);
    }
    public function templates(Request $request): JsonResponse
    {
        $this->authorizeSelector($request); $search=trim((string)$request->input('q','')); $items=Template::query()->where('ativo',1)->when($search!=='',fn(Builder $q):Builder=>$q->where(fn(Builder $f):Builder=>$f->where('nome','like',"%{$search}%")->orWhere('id','like',"%{$search}%")))->orderBy('nome')->paginate(20,['id','nome','pagina','layout_pagina'],'page',max((int)$request->input('page',1),1));
        return response()->json(['results'=>collect($items->items())->map(fn(Template $t):array=>['id'=>$t->id,'text'=>$this->templateLabel($t)])->values(),'pagination'=>['more'=>$items->hasMorePages()]]);
    }
    public function create(): View { return view('assinaturas_template.form',['assinatura'=>new AssinaturaTemplate()]); }
    public function store(Request $request): RedirectResponse { $assinatura=AssinaturaTemplate::query()->create($this->validated($request)); return redirect()->route('assinaturas_template.show',$assinatura)->with('status','Assinatura cadastrada com sucesso.'); }
    public function show(AssinaturaTemplate $assinatura): View { $assinatura->load(['participante','template','rubrica']); return view('assinaturas_template.show',compact('assinatura')); }
    public function edit(AssinaturaTemplate $assinatura): View { $assinatura->load(['participante','template']); return view('assinaturas_template.form',compact('assinatura')); }
    public function update(Request $request, AssinaturaTemplate $assinatura): RedirectResponse { $assinatura->update($this->validated($request)); return redirect()->route('assinaturas_template.show',$assinatura)->with('status','Assinatura atualizada com sucesso.'); }
    public function toggleStatus(AssinaturaTemplate $assinatura): RedirectResponse { $assinatura->update(['ativo'=>!$assinatura->ativo]); return redirect()->route('assinaturas_template.index')->with('status','Status atualizado com sucesso.'); }
    public function destroy(AssinaturaTemplate $assinatura): RedirectResponse { $assinatura->delete(); return redirect()->route('assinaturas_template.index')->with('status','Assinatura excluída com sucesso.'); }
    public function forceDestroy(int $assinatura): RedirectResponse { AssinaturaTemplate::withTrashed()->findOrFail($assinatura)->forceDelete(); return redirect()->route('assinaturas_template.index')->with('status','Assinatura excluída definitivamente.'); }
    private function validated(Request $request): array { return $request->validate(['participante_id'=>['nullable','integer',Rule::exists('participantes','id')->whereNull('excluido_em')],'template_id'=>['nullable','integer',Rule::exists('templates','id')->where(fn($q)=>$q->where('ativo',1)->whereNull('apagado_em'))],'titulacao'=>['nullable','string','max:20'],'rubrica_id'=>['nullable','integer',Rule::exists('rubricas_participantes','id')->whereNull('apagado_em')],'ativo'=>['required','boolean']]); }
    private function authorizeSelector(Request $request): void { $p=(array)$request->session()->get('gi_context.permissoes',[]); abort_unless(in_array('assinaturas.criar',$p,true)||in_array('assinaturas.editar',$p,true),403); }
    private function templateLabel(?Template $template): string { return $template ? "#{$template->id} - ".($template->nome?:'Sem nome')." - (".($template->pagina?:'—').' - '.($template->layout_pagina?:'—').')' : '—'; }
}
