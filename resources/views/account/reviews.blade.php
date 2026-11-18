@php use App\Models\ProductReview; @endphp
<x-account-layout :title="$settings['account_reviews_title']" :text="$settings['account_reviews_text']">
    @if ($waiting->isNotEmpty())
        <section class="card p-5 sm:p-6">
            <h2 class="flex items-center gap-3 font-bold"><span class="flex size-8 items-center justify-center rounded-xl bg-accent-soft text-accent-ink"><x-icon name="star" class="size-4" /></span>{{ $settings['account_reviews_waiting'] }}
                <span class="ml-auto rounded-full bg-surface px-2.5 py-0.5 text-xs font-semibold text-muted">{{ $waiting->count() }}</span></h2>
            <ul class="mt-4 divide-y divide-line">
                @foreach ($waiting as $product)
                    <li class="flex items-center gap-4 py-3">
                        <span class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-surface ring-1 ring-line">
                            @if ($product->image_key)<img src="{{ $product->imageUrl('sm') }}" alt="" class="max-h-full max-w-full object-contain">@else<x-icon name="book" class="size-6 text-slate-300" />@endif
                        </span>
                        <span class="min-w-0 flex-1">
                            <a href="{{ route('product.show', $product) }}" class="block truncate font-semibold hover:text-primary">{{ $product->title }}</a>
                            @if ($product->publisher)<span class="text-xs text-muted">{{ $product->publisher->name }}</span>@endif
                        </span>
                        <a href="{{ route('product.show', $product) }}#degerlendirme-yaz" class="btn-primary btn-sm shrink-0"><x-icon name="star" class="size-4" />{{ $settings['reviews_rate_cta'] }}</a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="{{ $waiting->isNotEmpty() ? 'mt-6' : '' }}">
        <h2 class="mb-3 font-bold">{{ $settings['account_reviews_mine'] }}</h2>
        @forelse ($reviews as $review)
            <article class="card mb-3 p-5">
                <div class="flex flex-wrap items-start gap-4">
                    <a href="{{ route('product.show', $review->product) }}" class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-surface ring-1 ring-line">
                        @if ($review->product->image_key)<img src="{{ $review->product->imageUrl('sm') }}" alt="" class="max-h-full max-w-full object-contain">@else<x-icon name="book" class="size-6 text-slate-300" />@endif
                    </a>
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('product.show', $review->product) }}" class="font-semibold hover:text-primary">{{ $review->product->title }}</a>
                        <p class="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted"><x-stars :value="$review->rating" size="size-3.5" />{{ $review->submitted_at?->translatedFormat('j F Y') }}
                            <span class="badge {{ ProductReview::tone($review->status) }}">{{ $review->statusLabel() }}</span></p>
                    </div>
                    <a href="{{ route('product.show', $review->product) }}#{{ $review->isPublished() && $review->status === 'approved' ? $review->anchor() : 'degerlendirme-yaz' }}" class="btn-ghost btn-sm shrink-0"><x-icon name="edit" class="size-4" />{{ $settings['reviews_edit'] }}</a>
                </div>
                @if ($review->title)<p class="mt-3 font-semibold">{{ $review->title }}</p>@endif
                <p class="mt-1 text-sm whitespace-pre-line text-ink/85">{{ \Illuminate\Support\Str::limit($review->body, 400) }}</p>
                @if ($review->status === 'rejected')<p class="mt-3 rounded-xl bg-danger-soft px-3 py-2 text-sm text-danger">{{ $settings['reviews_rejected_note'] }}@if ($review->reject_reason) “{{ $review->reject_reason }}”@endif</p>
                @elseif ($review->isPendingEdit())<p class="mt-3 text-sm text-muted">{{ $settings['reviews_pending_edit_note'] }}</p>
                @elseif ($review->status === 'pending')<p class="mt-3 text-sm text-muted">{{ $settings['reviews_pending_note'] }}</p>@endif
                @if ($review->reply)
                    <div class="pf-reply"><p class="text-xs font-bold text-primary-dark">{{ $settings['reviews_store_reply'] }}</p><p class="mt-1 text-sm whitespace-pre-line">{{ $review->reply }}</p></div>
                @endif
            </article>
        @empty
            @include('account._empty', ['icon' => 'star', 'title' => $settings['account_reviews_empty']])
        @endforelse
    </section>
</x-account-layout>
