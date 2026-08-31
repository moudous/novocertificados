<?php

namespace App\Http\Controllers;

use App\Models\BibliotecaImagem;
use App\Models\Template;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BibliotecaImagemController extends Controller
{
    private const CATEGORIES=['fundo'=>'Fundo','logotipo'=>'Logotipo','selo'=>'Selo','assinatura_institucional'=>'Assinatura institucional','decorativo'=>'Elemento decorativo','outro'=>'Outro'];
    public function index(Request $r): View { return view('biblioteca_imagens.index',['permissions'=>(array)$r->session()->get('gi_context.permissoes',[])]); }
    public function data(Request $r): JsonResponse { $q=BibliotecaImagem::withTrashed();$total=(clone$q)->count();$s=trim((string)$r->input('search.value',''));if($s!=='')$q->where(fn(Builder $x)=>$x->where('nome','like',"%$s%")->orWhere('categoria','like',"%$s%"));$filtered=(clone$q)->count();$p=(array)$r->session()->get('gi_context.permissoes',[]);$data=$q->orderByDesc('id')->skip(max((int)$r->input('start'),0))->take(min(max((int)$r->input('length',10),1),100))->get()->map(fn($i)=>['id'=>$i->id,'imagem'=>$i->url()?'<img src="'.e($i->url()).'" style="width:90px;height:55px;object-fit:contain" alt="">':'—','nome'=>e($i->nome),'categoria'=>e(self::CATEGORIES[$i->categoria]??$i->categoria),'ativo'=>$i->trashed()?'<span class="badge text-bg-danger">Excluída</span>':($i->ativo?'<span class="badge text-bg-success">Ativa</span>':'<span class="badge text-bg-secondary">Inativa</span>'),'acoes'=>view('biblioteca_imagens.actions',['imagem'=>$i,'permissions'=>$p,'usedTemplates'=>$this->usedByTemplates($i)])->render()]);return response()->json(['draw'=>(int)$r->input('draw'),'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$data]); }
    public function create(): View { return view('biblioteca_imagens.form',['imagem'=>new BibliotecaImagem(),'categories'=>self::CATEGORIES]); }
    public function store(Request $r): RedirectResponse { $d=$this->validated($r);$d=array_merge($d,$this->storeFile($r));$i=BibliotecaImagem::create($d);return redirect()->route('biblioteca_imagens.index')->with('status','Imagem adicionada à biblioteca.'); }
    public function edit(BibliotecaImagem $imagem): View { return view('biblioteca_imagens.form',['imagem'=>$imagem,'categories'=>self::CATEGORIES]); }
    public function update(Request $r,BibliotecaImagem $imagem): RedirectResponse { $d=$this->validated($r,true);if($r->hasFile('imagem')){$old=$imagem->path();$d=array_merge($d,$this->storeFile($r));File::delete($old);}$imagem->update($d);return redirect()->route('biblioteca_imagens.index')->with('status','Imagem atualizada.'); }
    public function toggle(BibliotecaImagem $imagem): RedirectResponse {$imagem->update(['ativo'=>!$imagem->ativo]);return back()->with('status','Status atualizado.');}
    public function destroy(BibliotecaImagem $imagem): RedirectResponse {$imagem->delete();return back()->with('status','Imagem excluída.');}
    public function restore(int $imagem): RedirectResponse { BibliotecaImagem::onlyTrashed()->findOrFail($imagem)->restore();return back()->with('status','Imagem restaurada com sucesso.'); }
    public function forceDestroy(int $imagem): RedirectResponse
    {
        $model=BibliotecaImagem::withTrashed()->findOrFail($imagem);$templates=$this->usedByTemplates($model);
        if($templates->isNotEmpty()) return back()->withErrors(['imagem'=>'Não é possível excluir definitivamente. A imagem está sendo usada nos templates: '.$templates->implode(', ').'.']);
        File::delete($model->path());$model->forceDelete();
        return back()->with('status','Imagem e arquivo físico excluídos definitivamente.');
    }
    private function validated(Request $r,bool $editing=false): array
    {
        $data=$r->validate(['nome'=>['required','string','max:120'],'categoria'=>['required',Rule::in(array_keys(self::CATEGORIES))],'imagem'=>[$editing?'nullable':'required','image','mimes:png,jpg,jpeg,webp','max:10240'],'svg'=>['nullable','string','max:5242880'],'ativo'=>['required','boolean']]);
        $data['svg']=$this->sanitizeSvg($data['svg']??null);
        return $data;
    }

    private function sanitizeSvg(?string $svg): ?string
    {
        $svg=trim((string)$svg);
        if($svg==='')return null;
        if(!class_exists(\DOMDocument::class))throw ValidationException::withMessages(['svg'=>'A extensão DOM do PHP é necessária para validar o SVG.']);
        $previous=libxml_use_internal_errors(true);$document=new \DOMDocument();
        $loaded=$document->loadXML($svg,LIBXML_NONET|LIBXML_NOBLANKS);libxml_clear_errors();libxml_use_internal_errors($previous);
        if(!$loaded||$document->documentElement?->localName!=='svg')throw ValidationException::withMessages(['svg'=>'O código SVG informado é inválido.']);
        $allowedElements=['svg','g','path','title','desc'];$allowedAttributes=['xmlns','viewBox','width','height','fill','fill-rule','data-color'];
        foreach(iterator_to_array($document->getElementsByTagName('*')) as $element){
            if(!in_array($element->localName,$allowedElements,true))throw ValidationException::withMessages(['svg'=>'O SVG contém elementos não permitidos.']);
            foreach(iterator_to_array($element->attributes??[]) as $attribute){
                if(!in_array($attribute->name,$allowedAttributes,true)&&!($element->localName==='path'&&$attribute->name==='d'))throw ValidationException::withMessages(['svg'=>'O SVG contém atributos não permitidos.']);
            }
        }
        $root=$document->documentElement;$viewBox=$root->getAttribute('viewBox');
        if(!preg_match('/^0 0 [1-9]\d{0,3} [1-9]\d{0,3}$/',$viewBox))throw ValidationException::withMessages(['svg'=>'As dimensões do SVG são inválidas.']);
        foreach($document->getElementsByTagName('path') as $path){
            if(!preg_match('/^#[0-9A-Fa-f]{6}$/',$path->getAttribute('fill'))||!preg_match('/^[MZHVa-z0-9., \-]+$/',$path->getAttribute('d')))throw ValidationException::withMessages(['svg'=>'Um caminho ou uma cor do SVG é inválido.']);
        }
        return $document->saveXML($root);
    }
    private function storeFile(Request $r): array{$f=$r->file('imagem');$ext=strtolower($f->getClientOriginalExtension());$name=hash('sha1',Str::uuid()->toString()).'.'.$ext;$dir=public_path('certificado/biblioteca');File::ensureDirectoryExists($dir);$f->move($dir,$name);$size=@getimagesize($dir.'/'.$name);return ['arquivo'=>$name,'mime_type'=>File::mimeType($dir.'/'.$name),'largura_px'=>$size[0]??null,'altura_px'=>$size[1]??null,'tamanho'=>File::size($dir.'/'.$name)];}
    private function usedByTemplates(BibliotecaImagem $imagem)
    {
        return Template::withTrashed()->get(['id','nome','biblioteca_imagem_id','elementos_layout'])->filter(function(Template $template)use($imagem):bool{
            if((int)$template->biblioteca_imagem_id===(int)$imagem->id)return true;
            return collect($template->elementos_layout??[])->contains(fn($element):bool=>is_array($element)&&(int)($element['library_image_id']??0)===(int)$imagem->id);
        })->map(fn(Template $template):string=>($template->nome?:'Template sem nome').' (#'.$template->id.')')->values();
    }
}
