<?php

namespace App\Http\Controllers;

use App\Models\BibliotecaImagem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BibliotecaImagemController extends Controller
{
    private const CATEGORIES=['fundo'=>'Fundo','logotipo'=>'Logotipo','selo'=>'Selo','assinatura_institucional'=>'Assinatura institucional','decorativo'=>'Elemento decorativo','outro'=>'Outro'];
    public function index(Request $r): View { return view('biblioteca_imagens.index',['permissions'=>(array)$r->session()->get('gi_context.permissoes',[])]); }
    public function data(Request $r): JsonResponse { $q=BibliotecaImagem::withTrashed();$total=(clone$q)->count();$s=trim((string)$r->input('search.value',''));if($s!=='')$q->where(fn(Builder $x)=>$x->where('nome','like',"%$s%")->orWhere('categoria','like',"%$s%"));$filtered=(clone$q)->count();$p=(array)$r->session()->get('gi_context.permissoes',[]);$data=$q->orderByDesc('id')->skip(max((int)$r->input('start'),0))->take(min(max((int)$r->input('length',10),1),100))->get()->map(fn($i)=>['id'=>$i->id,'imagem'=>$i->url()?'<img src="'.e($i->url()).'" style="width:90px;height:55px;object-fit:contain" alt="">':'—','nome'=>e($i->nome),'categoria'=>e(self::CATEGORIES[$i->categoria]??$i->categoria),'ativo'=>$i->trashed()?'<span class="badge text-bg-danger">Excluída</span>':($i->ativo?'<span class="badge text-bg-success">Ativa</span>':'<span class="badge text-bg-secondary">Inativa</span>'),'acoes'=>view('biblioteca_imagens.actions',['imagem'=>$i,'permissions'=>$p])->render()]);return response()->json(['draw'=>(int)$r->input('draw'),'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$data]); }
    public function create(): View { return view('biblioteca_imagens.form',['imagem'=>new BibliotecaImagem(),'categories'=>self::CATEGORIES]); }
    public function store(Request $r): RedirectResponse { $d=$this->validated($r);$d=array_merge($d,$this->storeFile($r));$i=BibliotecaImagem::create($d);return redirect()->route('biblioteca_imagens.index')->with('status','Imagem adicionada à biblioteca.'); }
    public function edit(BibliotecaImagem $imagem): View { return view('biblioteca_imagens.form',['imagem'=>$imagem,'categories'=>self::CATEGORIES]); }
    public function update(Request $r,BibliotecaImagem $imagem): RedirectResponse { $d=$this->validated($r,true);if($r->hasFile('imagem')){$old=$imagem->path();$d=array_merge($d,$this->storeFile($r));File::delete($old);}$imagem->update($d);return redirect()->route('biblioteca_imagens.index')->with('status','Imagem atualizada.'); }
    public function toggle(BibliotecaImagem $imagem): RedirectResponse {$imagem->update(['ativo'=>!$imagem->ativo]);return back()->with('status','Status atualizado.');}
    public function destroy(BibliotecaImagem $imagem): RedirectResponse {$imagem->delete();return back()->with('status','Imagem excluída.');}
    private function validated(Request $r,bool $editing=false): array{return $r->validate(['nome'=>['required','string','max:120'],'categoria'=>['required',Rule::in(array_keys(self::CATEGORIES))],'imagem'=>[$editing?'nullable':'required','image','mimes:png,jpg,jpeg,webp','max:10240'],'ativo'=>['required','boolean']]);}
    private function storeFile(Request $r): array{$f=$r->file('imagem');$ext=strtolower($f->getClientOriginalExtension());$name=hash('sha1',Str::uuid()->toString()).'.'.$ext;$dir=public_path('certificado/biblioteca');File::ensureDirectoryExists($dir);$f->move($dir,$name);$size=@getimagesize($dir.'/'.$name);return ['arquivo'=>$name,'mime_type'=>File::mimeType($dir.'/'.$name),'largura_px'=>$size[0]??null,'altura_px'=>$size[1]??null,'tamanho'=>File::size($dir.'/'.$name)];}
}
