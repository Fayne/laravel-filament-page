<ol style="padding-left:1.5rem;list-style:decimal">
@foreach ($blocks as $block)
<li>{{ $block['internalTitle'] ?? $block['layout'] }} <small>{{ $block['layout'] }}</small>
@foreach ($block['attributes'] ?? [] as $name => $children)
<div>{{ $name }}</div>
@include('filament-page::template-structure-blocks', ['blocks' => $children])
@endforeach
</li>
@endforeach
</ol>
