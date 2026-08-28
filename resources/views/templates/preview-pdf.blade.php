<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><style>
@page{margin:0}html,body{margin:0;padding:0;width:{{ $width }}mm;height:{{ $height }}mm;overflow:hidden;font-family:DejaVu Sans,sans-serif}.page{position:relative;width:{{ $width }}mm;height:{{ $height }}mm;overflow:hidden;@if($background)background-image:url('{{ $background }}');background-repeat:no-repeat;background-position:0 0;background-size:{{ $width }}mm {{ $height }}mm;@endif}.element{position:absolute;overflow:hidden;box-sizing:border-box}.element-text{white-space:pre-wrap;line-height:1.15;font-size:12pt}.element-image img{display:block;width:100%;height:100%;object-fit:contain}
</style></head><body><div class="page">
@foreach($elements as $element)
    @php($alignment=['esquerda'=>'left','direita'=>'right','centralizado'=>'center','justificado'=>'justify'][$element['align']] ?? 'left')
    <div class="element element-{{ $element['type']==='imagem'?'image':'text' }}" style="left:{{ $element['x'] }}mm;top:{{ $element['y'] }}mm;width:{{ $element['width'] }}mm;height:{{ $element['height'] }}mm;@if($element['type']==='texto')color:{{ $element['color'] }};text-align:{{ $alignment }}@endif">@if($element['type']==='imagem' && $element['image'])<img src="{{ $element['image'] }}" alt="">@elseif($element['type']==='texto'){{ $element['text'] }}@endif</div>
@endforeach
</div></body></html>
