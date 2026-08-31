<?php

namespace App\Http\Controllers;

use App\Models\BibliotecaImagem;
use App\Models\ImagemTemplate;
use App\Models\Template;
use App\Services\SvgSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ImagemTemplateController extends Controller
{
    public function store(Request $request,Template $template,SvgSanitizer $sanitizer): JsonResponse
    {
        $data=$request->validate(['id'=>['nullable','integer'],'biblioteca_imagem_id'=>['required','integer',Rule::exists('biblioteca_imagens','id')->where(fn($q)=>$q->whereNotNull('svg')->whereNull('apagado_em'))],'element_uid'=>['required','string','max:80'],'svg'=>['required','string','max:5242880']]);
        $library=BibliotecaImagem::findOrFail($data['biblioteca_imagem_id']);$svg=$sanitizer->sanitize($data['svg']);
        $image=filled($data['id']??null)?$template->imagensTemplate()->findOrFail((int)$data['id']):$template->imagensTemplate()->firstOrNew(['element_uid'=>$data['element_uid']]);
        $image->fill(['biblioteca_imagem_id'=>$library->id,'element_uid'=>$data['element_uid'],'nome'=>$library->nome.' · personalizada','svg'=>$svg]);$image->save();
        return response()->json(['image'=>['id'=>$image->id,'name'=>$image->nome,'svg'=>$image->svg,'url'=>$image->dataUrl(),'library_image_id'=>$image->biblioteca_imagem_id,'element_uid'=>$image->element_uid]]);
    }
    public function destroy(Template $template,int $image): JsonResponse{$model=$template->imagensTemplate()->findOrFail($image);$model->delete();return response()->json(['deleted'=>true]);}
}
