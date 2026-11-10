@php
    $url = match ($block->setting('source')) {
        'category' => ($c = \App\Models\Category::find($block->setting('category_id'))) ? route('category.show', $c) : null,
        'publisher' => ($p = \App\Models\Publisher::find($block->setting('publisher_id'))) ? route('publisher.show', $p) : null,
        'newest' => route('products.index', ['sirala' => 'yeni']),
        'bestsellers' => route('products.index', ['sirala' => 'cok-satan']),
        default => null,
    };
@endphp
<x-product-row :title="$block->title ?: \App\Models\HomeBlock::PRODUCT_SOURCES[$block->setting('source')] ?? 'Ürünler'" :subtitle="$block->subtitle"
               :products="$data" :url="$url" />
