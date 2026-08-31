<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class SvgSanitizer
{
    public function sanitize(?string $svg): ?string
    {
        $svg=trim((string)$svg);if($svg==='')return null;
        $previous=libxml_use_internal_errors(true);$document=new \DOMDocument();
        $loaded=$document->loadXML($svg,LIBXML_NONET|LIBXML_NOBLANKS);libxml_clear_errors();libxml_use_internal_errors($previous);
        $invalid=fn(string $message)=>throw ValidationException::withMessages(['svg'=>$message]);
        if(!$loaded||$document->documentElement?->localName!=='svg')$invalid('O código SVG informado é inválido.');
        $allowedElements=['svg','g','path','title','desc'];$allowedAttributes=['xmlns','viewBox','width','height','fill','fill-rule','data-color'];
        foreach(iterator_to_array($document->getElementsByTagName('*')) as $element){
            if(!in_array($element->localName,$allowedElements,true))$invalid('O SVG contém elementos não permitidos.');
            foreach(iterator_to_array($element->attributes??[]) as $attribute)if(!in_array($attribute->name,$allowedAttributes,true)&&!($element->localName==='path'&&$attribute->name==='d'))$invalid('O SVG contém atributos não permitidos.');
        }
        if(!preg_match('/^0 0 [1-9]\d{0,3} [1-9]\d{0,3}$/',$document->documentElement->getAttribute('viewBox')))$invalid('As dimensões do SVG são inválidas.');
        foreach($document->getElementsByTagName('path') as $path)if(!preg_match('/^#[0-9A-Fa-f]{6}$/',$path->getAttribute('fill'))||!preg_match('/^[MZHVa-z0-9., \-]+$/',$path->getAttribute('d')))$invalid('Um caminho ou uma cor do SVG é inválido.');
        return $document->saveXML($document->documentElement);
    }
}
