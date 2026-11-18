{{--
    Ürün sayfası alt bölümü: "Değerlendirmeler" ve "Soru & Cevap" sekmeleri. $reviewData / $questionData denetleyiciden
    (ayarlarda kapalı olan null gelir). Değerlendirmeyi yalnız ürünü teslim almış üye yazar; soruyu herkes sorar.
    Bağlantılar: #degerlendirmeler, #degerlendirme-yaz, #degerlendirme-{id}, #sorular, #soru-sor, #soru-{id}.
--}}
@php
    /** @var \App\Models\Product $product */
    $r = $reviewData;
    $q = $questionData;
    $questionErrors = $errors->getBag('question');
    $initialTab = ! $r || ($q && (request()->has('sorular') || session('question_status') || $questionErrors->isNotEmpty())) ? 'questions' : 'reviews';
    $both = $r && $q;
    $mine = $r['eligibility']['review'] ?? null;
    $state = $r['eligibility']['state'] ?? null;
    $reviewErrors = $errors->getBag('default');
    $openForm = $reviewErrors->isNotEmpty();
    $num = fn ($n) => number_format((float) $n, 1, ',', '');
    $sortUrl = fn (array $p) => request()->fullUrlWithQuery(array_merge(['yorumlar' => null], $p)).'#degerlendirmeler';
@endphp
<section id="degerlendirmeler" class="product-feedback container-page py-10 sm:py-14" x-data="productTabs" data-tab="{{ $initialTab }}">
    @if ($both)
        <div class="pf-tabs" role="tablist">
            <button type="button" role="tab" class="pf-tab" x-on:click="showReviews" x-bind:aria-selected="reviewsSelected" aria-selected="{{ $initialTab === 'reviews' ? 'true' : 'false' }}" aria-controls="pf-reviews">
                <x-icon name="star" class="size-4" />{{ $settings['reviews_tab'] }}<span class="pf-tab-count">{{ $r['total'] }}</span>
            </button>
            <button type="button" role="tab" class="pf-tab" x-on:click="showQuestions" x-bind:aria-selected="questionsSelected" aria-selected="{{ $initialTab === 'questions' ? 'true' : 'false' }}" aria-controls="sorular">
                <x-icon name="chat" class="size-4" />{{ $settings['questions_tab'] }}<span class="pf-tab-count">{{ $q['list']->total() }}</span>
            </button>
        </div>
    @endif

    {{-- ================= Değerlendirmeler ================= --}}
    @if ($r)
        <div id="pf-reviews" role="tabpanel" @if ($both) x-show="isReviews" @if ($initialTab !== 'reviews') x-cloak @endif @endif class="pf-panel">
            @unless ($both)<h2 class="pf-heading">{{ $settings['reviews_tab'] }}</h2>@endunless
            @if (session('review_status'))
                <p class="pf-notice pf-notice-success" role="status"><x-icon name="check" class="size-4 shrink-0" />{{ session('review_status') }}</p>
            @endif
            @if ($reviewErrors->has('review'))
                <p class="pf-notice pf-notice-danger" role="alert"><x-icon name="alert" class="size-4 shrink-0" />{{ $reviewErrors->first('review') }}</p>
            @endif

            <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,340px)_minmax(0,1fr)] lg:gap-10">
                {{-- Özet ve yazma durumu --}}
                <aside class="pf-summary lg:sticky lg:top-36">
                    <p class="text-xs font-bold tracking-[0.22em] text-accent-ink uppercase">{{ $settings['reviews_summary_title'] }}</p>
                    @if ($r['total'])
                        <div class="mt-3 flex items-end gap-4">
                            <p class="font-display text-6xl leading-none font-extrabold tracking-tight">{{ $num($r['average']) }}</p>
                            <div class="pb-1">
                                <x-stars :value="$r['average']" size="size-5" />
                                <p class="mt-1 text-sm text-muted">{{ str_replace(':n', $r['total'], $settings['reviews_count']) }}</p>
                            </div>
                        </div>
                        <ul class="mt-5 space-y-1.5">
                            @foreach ($r['distribution'] as $stars => $count)
                                @php $pct = $r['total'] ? round($count / $r['total'] * 100) : 0; @endphp
                                <li class="flex items-center gap-3 text-sm">
                                    <span class="flex w-8 shrink-0 items-center gap-1 font-semibold">{{ $stars }}<x-icon name="star" class="size-3.5 text-accent" /></span>
                                    <span class="pf-bar"><span class="pf-bar-fill" style="width: {{ $pct }}%"></span></span>
                                    <span class="w-9 shrink-0 text-right text-xs text-muted tabular-nums">{{ $count }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="mt-3 flex items-center gap-3">
                            <x-stars :value="0" size="size-6" />
                        </div>
                        <p class="mt-3 font-semibold">{{ $settings['reviews_empty'] }}</p>
                        <p class="mt-1 text-sm text-muted">{{ $settings['reviews_empty_sub'] }}</p>
                    @endif

                    <div class="mt-6 border-t border-line pt-5">
                        @if ($state === 'guest')
                            <p class="text-sm text-muted">{{ $settings['reviews_login_note'] }}</p>
                            <a href="{{ route('login') }}" class="btn-ghost mt-3 w-full"><x-icon name="user" class="size-4" />{{ $settings['reviews_login_button'] }}</a>
                        @elseif ($state === 'not_bought' || $state === 'not_delivered')
                            <p class="flex items-start gap-2 text-sm text-muted"><x-icon name="lock" class="mt-0.5 size-4 shrink-0" />{{ $settings[$state === 'not_bought' ? 'reviews_not_bought' : 'reviews_not_delivered'] }}</p>
                        @elseif ($mine)
                            <p class="text-sm font-semibold">{{ $settings['reviews_yours'] }}</p>
                            <div class="mt-2 flex items-center gap-2"><x-stars :value="$mine->rating" size="size-4" /><span class="badge {{ \App\Models\ProductReview::tone($mine->status) }}">{{ $mine->statusLabel() }}</span></div>
                            @if ($mine->isPendingEdit())<p class="mt-2 text-sm text-muted">{{ $settings['reviews_pending_edit_note'] }}</p>
                            @elseif ($mine->status === 'pending')<p class="mt-2 text-sm text-muted">{{ $settings['reviews_pending_note'] }}</p>
                            @elseif ($mine->status === 'rejected')<p class="mt-2 text-sm text-danger">{{ $settings['reviews_rejected_note'] }}@if ($mine->reject_reason) “{{ $mine->reject_reason }}”@endif</p>@endif
                            <div class="mt-3 flex flex-wrap gap-2">
                                <a href="#degerlendirme-yaz" class="btn-ghost btn-sm"><x-icon name="edit" class="size-4" />{{ $settings['reviews_edit'] }}</a>
                                <form method="post" action="{{ route('product.reviews.destroy', $product) }}" data-confirm="{{ $settings['reviews_delete_confirm'] }}">
                                    @csrf @method('delete')
                                    <button class="btn-ghost btn-sm text-danger"><x-icon name="trash" class="size-4" />{{ $settings['reviews_delete'] }}</button>
                                </form>
                            </div>
                        @else
                            <a href="#degerlendirme-yaz" class="btn-primary w-full"><x-icon name="star" class="size-4" />{{ $settings['reviews_write'] }}</a>
                        @endif
                    </div>
                </aside>

                <div class="min-w-0 space-y-5">
                    {{-- Yazma / düzenleme formu --}}
                    @if ($state === 'ok')
                        @php
                            $rating = (int) old('rating', $mine?->rating);
                            $photos = $mine?->photos ?? collect();
                            $room = \App\Domain\Reviews\ReviewPhotos::MAX;
                        @endphp
                        <details id="degerlendirme-yaz" class="pf-form" @if ($openForm) open @endif>
                            <summary class="pf-form-summary">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-accent-soft text-accent-ink"><x-icon name="{{ $mine ? 'edit' : 'star' }}" class="size-5" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-display text-lg font-bold">{{ $mine ? $settings['reviews_edit'] : $settings['reviews_form_title'] }}</span>
                                    <span class="block text-sm text-muted">{{ $settings['reviews_form_note'] }}</span>
                                </span>
                                <x-icon name="chevron-down" class="pf-form-chevron size-5 shrink-0 text-muted" />
                            </summary>
                            <form method="post" action="{{ route('product.reviews.store', $product) }}" enctype="multipart/form-data" class="space-y-5 border-t border-line p-5 sm:p-6">
                                @csrf
                                <fieldset x-data="ratingInput" data-labels="{{ implode('|', $r['labels']) }}" data-value="{{ $rating }}">
                                    <legend class="field-label">{{ $settings['reviews_rating_label'] }}<span class="text-danger"> *</span></legend>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <div class="rating-input" x-on:change="pick">
                                            @foreach (range(5, 1) as $v)
                                                <input type="radio" name="rating" value="{{ $v }}" id="rating-{{ $v }}" @checked($rating === $v) required>
                                                <label for="rating-{{ $v }}" title="{{ $r['labels'][$v - 1] ?? $v }}"><span class="sr-only">{{ $v }} yıldız</span><svg viewBox="0 0 24 24" class="size-8" aria-hidden="true"><path d="M12 2.5l2.94 5.96 6.58.96-4.76 4.64 1.12 6.55L12 17.52l-5.88 3.09 1.12-6.55L2.48 9.42l6.58-.96L12 2.5z" /></svg></label>
                                            @endforeach
                                        </div>
                                        <span class="text-sm font-semibold text-accent-ink" x-text="text">{{ $rating ? ($r['labels'][$rating - 1] ?? '') : '' }}</span>
                                    </div>
                                    @error('rating')<p class="field-error">{{ $message }}</p>@enderror
                                </fieldset>
                                <x-field name="title" :label="$settings['reviews_title_label']" :value="$mine?->title" :placeholder="$settings['reviews_title_placeholder']" maxlength="120" />
                                <x-field name="body" type="textarea" rows="5" :label="$settings['reviews_body_label']" :value="$mine?->body" :placeholder="$settings['reviews_body_placeholder']" required minlength="10" maxlength="3000" />

                                @if (\App\Support\Settings::flag('reviews_photos'))
                                    <div x-data="photoPicker" data-max="{{ $room - $photos->count() }}" data-warn="{{ str_replace(':n', $room, $settings['reviews_photo_limit']) }}">
                                        <span class="field-label">{{ $settings['reviews_photos_label'] }}</span>
                                        @if ($photos->isNotEmpty())
                                            <div class="mb-3 flex flex-wrap gap-2">
                                                @foreach ($photos as $photo)
                                                    <label class="pf-photo-edit">
                                                        <img src="{{ $photo->thumbUrl() }}" alt="" class="size-20 rounded-xl object-cover">
                                                        <span class="flex items-center gap-1.5 text-xs"><input type="checkbox" name="remove_photos[]" value="{{ $photo->id }}" class="accent-danger">{{ $settings['reviews_photo_remove'] }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        @endif
                                        <label class="pf-dropzone">
                                            <x-icon name="image" class="size-6 text-primary" />
                                            <span class="text-sm font-semibold">{{ $settings['reviews_photo_pick'] }}</span>
                                            <input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" class="sr-only" x-ref="input" x-on:change="pick">
                                        </label>
                                        <div class="mt-3 flex flex-wrap gap-2" x-show="hasPreviews" x-cloak>
                                            <template x-for="src in previews"><img x-bind:src="src" alt="" class="size-20 rounded-xl object-cover ring-1 ring-line"></template>
                                        </div>
                                        <p class="field-error" x-show="hasWarning" x-text="warning" x-cloak></p>
                                        @if ($reviewErrors->has('photos') || $reviewErrors->has('photos.*'))<p class="field-error">{{ $reviewErrors->first('photos') ?: collect($reviewErrors->get('photos.*'))->flatten()->first() }}</p>@endif
                                        <p class="field-help">{{ $settings['reviews_photos_note'] }}</p>
                                    </div>
                                @endif
                                <div class="flex justify-end"><button class="btn-primary"><x-icon name="send" class="size-4" />{{ $settings['reviews_submit'] }}</button></div>
                            </form>
                        </details>
                    @endif

                    {{-- Süzgeç ve sıralama --}}
                    @if ($r['total'])
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ $sortUrl(['fotografli' => null]) }}" class="pf-chip" @if (! $r['photoFilter']) aria-current="true" @endif>{{ $settings['reviews_filter_all'] }} <span>{{ $r['total'] }}</span></a>
                                @if ($r['photoCount'])
                                    <a href="{{ $sortUrl(['fotografli' => 1]) }}" class="pf-chip" @if ($r['photoFilter']) aria-current="true" @endif><x-icon name="image" class="size-3.5" />{{ $settings['reviews_filter_photos'] }} <span>{{ $r['photoCount'] }}</span></a>
                                @endif
                            </div>
                            <div class="flex gap-1 rounded-xl bg-surface p-1 text-sm">
                                @foreach (['yeni' => 'reviews_sort_new', 'yuksek' => 'reviews_sort_high', 'dusuk' => 'reviews_sort_low'] as $key => $label)
                                    <a href="{{ $sortUrl(['siralama' => $key === 'yeni' ? null : $key]) }}" class="pf-sort" @if ($r['sort'] === $key) aria-current="true" @endif>{{ $settings[$label] }}</a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Liste --}}
                    <div x-data="lightbox" x-on:keydown.escape.window="close" class="space-y-4">
                        @forelse ($r['list'] as $review)
                            <article id="{{ $review->anchor() }}" class="pf-review">
                                <header class="flex flex-wrap items-start gap-3">
                                    <span class="pf-avatar" aria-hidden="true">{{ mb_substr($review->authorName(), 0, 1) }}</span>
                                    <div class="min-w-0 flex-1">
                                        <p class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <span class="font-semibold">{{ $review->authorName() }}</span>
                                            @if ($review->order_id)<span class="pf-verified"><x-icon name="shield" class="size-3.5" />{{ $settings['reviews_verified'] }}</span>@endif
                                        </p>
                                        <p class="mt-0.5 flex items-center gap-2 text-xs text-muted"><x-stars :value="$review->published_rating" size="size-3.5" /><time datetime="{{ $review->published_at->toDateString() }}">{{ $review->published_at->translatedFormat('j F Y') }}</time></p>
                                    </div>
                                </header>
                                @if ($review->published_title)<h3 class="mt-3 font-display font-bold">{{ $review->published_title }}</h3>@endif
                                <p class="mt-2 text-[15px] leading-relaxed whitespace-pre-line break-words text-ink/85">{{ $review->published_body }}</p>
                                @if ($review->photos->isNotEmpty())
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach ($review->photos as $photo)
                                            <a href="{{ $photo->url() }}" target="_blank" rel="noopener" class="pf-thumb" x-on:click="open">
                                                <img src="{{ $photo->thumbUrl() }}" alt="{{ $review->authorName() }} fotoğrafı" loading="lazy" class="size-20 object-cover sm:size-24">
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                                @if ($review->reply)
                                    <div class="pf-reply">
                                        <p class="flex items-center gap-2 text-xs font-bold text-primary-dark"><x-icon name="chat" class="size-3.5" />{{ $settings['reviews_store_reply'] }} · {{ $settings['site_name'] }}</p>
                                        <p class="mt-1.5 text-sm leading-relaxed whitespace-pre-line break-words">{{ $review->reply }}</p>
                                    </div>
                                @endif
                            </article>
                        @empty
                            @if ($r['total'])<p class="pf-empty">{{ $settings['reviews_no_match'] }}</p>@endif
                        @endforelse

                        <div class="pf-lightbox" x-show="isOpen" x-on:click="close" x-transition.opacity x-cloak role="dialog" aria-modal="true">
                            <button type="button" class="pf-lightbox-close" x-on:click="close" aria-label="Kapat"><x-icon name="x" class="size-6" /></button>
                            <img x-bind:src="src" alt="" class="max-h-[88vh] max-w-[92vw] rounded-2xl object-contain shadow-2xl">
                        </div>
                    </div>
                    @if ($r['list']->hasPages())<div>{{ $r['list']->links() }}</div>@endif
                </div>
            </div>
        </div>
    @endif

    {{-- ================= Soru & Cevap ================= --}}
    @if ($q)
        <div id="sorular" role="tabpanel" @if ($both) x-show="isQuestions" @if ($initialTab !== 'questions') x-cloak @endif @endif class="pf-panel">
            @unless ($both)<h2 class="pf-heading">{{ $settings['questions_tab'] }}</h2>@endunless
            <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,380px)] lg:gap-10">
                <div class="min-w-0">
                    <div class="flex items-baseline justify-between gap-3">
                        <h3 class="font-display text-xl font-bold">{{ $settings['questions_title'] }}</h3>
                        @if ($q['list']->total())<span class="text-sm text-muted">{{ str_replace(':n', $q['list']->total(), $settings['questions_count']) }}</span>@endif
                    </div>
                    <div class="mt-4 space-y-4">
                        @forelse ($q['list'] as $question)
                            <article id="{{ $question->anchor() }}" class="pf-qa">
                                <div class="flex gap-3">
                                    <span class="pf-qa-badge pf-qa-q" aria-hidden="true">S</span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[15px] font-semibold break-words whitespace-pre-line">{{ $question->body }}</p>
                                        <p class="mt-1 text-xs text-muted">{{ $question->authorName() }} · {{ $question->created_at->translatedFormat('j F Y') }}</p>
                                    </div>
                                </div>
                                <div class="mt-3 flex gap-3">
                                    <span class="pf-qa-badge pf-qa-a" aria-hidden="true">C</span>
                                    <div class="min-w-0 flex-1 rounded-2xl bg-primary-soft/60 px-4 py-3">
                                        <p class="text-xs font-bold text-primary-dark">{{ $settings['questions_answer_label'] }} · {{ $settings['site_name'] }}</p>
                                        <p class="mt-1 text-sm leading-relaxed break-words whitespace-pre-line">{{ $question->answer }}</p>
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="pf-empty">
                                <x-icon name="chat" class="mx-auto size-8 text-slate-300" />
                                <p class="mt-2 font-semibold text-ink">{{ $settings['questions_empty'] }}</p>
                                <p class="mt-1 text-sm">{{ $settings['questions_empty_sub'] }}</p>
                            </div>
                        @endforelse
                    </div>
                    @if ($q['list']->hasPages())<div class="mt-4">{{ $q['list']->links() }}</div>@endif
                </div>

                <form id="soru-sor" method="post" action="{{ route('product.questions.store', $product) }}" class="pf-ask lg:sticky lg:top-36">
                    @csrf
                    <h3 class="flex items-center gap-3 font-display text-lg font-bold"><span class="flex size-10 items-center justify-center rounded-2xl bg-primary-soft text-primary"><x-icon name="help" class="size-5" /></span>{{ $settings['questions_form_title'] }}</h3>
                    <p class="mt-2 text-sm text-muted">{{ $settings['questions_form_note'] }}</p>
                    @if (session('question_status'))
                        <p class="pf-notice pf-notice-success mt-4" role="status"><x-icon name="check" class="size-4 shrink-0" />{{ session('question_status') }}</p>
                    @endif
                    <div class="mt-4 space-y-4">
                        {{-- Bot tuzağı --}}
                        <div class="hidden" aria-hidden="true"><label>Web sitesi<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                            <div>
                                <label for="q_name" class="field-label">{{ $settings['questions_name_label'] }}<span class="text-danger"> *</span></label>
                                <input id="q_name" name="name" value="{{ old('name', $q['user']?->name) }}" required maxlength="120" autocomplete="name" class="field-input" aria-invalid="{{ $questionErrors->has('name') ? 'true' : 'false' }}">
                                @if ($questionErrors->has('name'))<p class="field-error">{{ $questionErrors->first('name') }}</p>@endif
                            </div>
                            <div>
                                <label for="q_email" class="field-label">{{ $settings['questions_email_label'] }}<span class="text-danger"> *</span></label>
                                <input id="q_email" type="email" name="email" value="{{ old('email', $q['user']?->email) }}" required maxlength="190" autocomplete="email" class="field-input" aria-invalid="{{ $questionErrors->has('email') ? 'true' : 'false' }}">
                                @if ($questionErrors->has('email'))<p class="field-error">{{ $questionErrors->first('email') }}</p>@endif
                            </div>
                        </div>
                        <div>
                            <label for="q_body" class="field-label">{{ $settings['questions_body_label'] }}<span class="text-danger"> *</span></label>
                            <textarea id="q_body" name="body" rows="4" required minlength="10" maxlength="1000" placeholder="{{ $settings['questions_body_placeholder'] }}" class="field-input" aria-invalid="{{ $questionErrors->has('body') ? 'true' : 'false' }}">{{ old('body') }}</textarea>
                            @if ($questionErrors->has('body'))<p class="field-error">{{ $questionErrors->first('body') }}</p>@endif
                        </div>
                        <label class="flex items-start gap-2.5 text-sm text-muted">
                            <input type="checkbox" name="consent" value="1" @checked(old('consent')) required class="mt-0.5 size-4 shrink-0 accent-primary">
                            <span>{{ $settings['questions_consent'] }} <a href="{{ url('/sayfa/kvkk-aydinlatma-metni') }}" target="_blank" class="font-semibold text-primary hover:underline">{{ $settings['questions_kvkk_link'] }}</a></span>
                        </label>
                        @if ($questionErrors->has('consent'))<p class="field-error">{{ $questionErrors->first('consent') }}</p>@endif
                        <button class="btn-primary w-full"><x-icon name="send" class="size-4" />{{ $settings['questions_submit'] }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</section>
