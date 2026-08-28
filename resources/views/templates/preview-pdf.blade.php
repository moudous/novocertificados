<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><style>
@foreach($fonts as $font)@font-face{font-family:'{{ str_replace("'",'', $font['name']) }}';src:url('{{ $font['data'] }}')}@endforeach
@page{margin:0}html,body{margin:0;padding:0;width:{{ $width }}mm;height:{{ $height }}mm;overflow:hidden;font-family:DejaVu Sans,sans-serif}.page{position:relative;width:{{ $width }}mm;height:{{ $height }}mm;overflow:hidden;@if($background)background-image:url('{{ $background }}');background-repeat:no-repeat;background-position:0 0;background-size:{{ $width }}mm {{ $height }}mm;@endif}.element{position:absolute;overflow:hidden;box-sizing:border-box}.element-text{white-space:pre-wrap;line-height:1.15}.element-image img{display:block;width:100%;height:100%;object-fit:contain}
</style></head><body><div class="page">
@foreach($elements as $element)
    @php($alignment=['esquerda'=>'left','direita'=>'right','centralizado'=>'center','justificado'=>'justify'][$element['align']] ?? 'left')
    <div class="element element-{{ $element['type']==='imagem'?'image':'text' }}" style="left:{{ $element['x'] }}mm;top:{{ $element['y'] }}mm;width:{{ $element['width'] }}mm;height:{{ $element['height'] }}mm;@if($element['type']==='texto')color:{{ $element['color'] }};text-align:{{ $alignment }};font-family:'{{ str_replace("'",'', $element['font_family']) }}';font-size:{{ $element['font_size'] }}pt;font-weight:{{ $element['bold']?'bold':'normal' }};font-style:{{ $element['italic']?'italic':'normal' }};text-decoration:{{ $element['underline']?'underline':'none' }}@endif">@if($element['type']==='imagem' && $element['image'])<img src="{{ $element['image'] }}" alt="">@elseif($element['type']==='texto'){{ $element['text'] }}@endif</div>
@endforeach
</div></body></html>
