{{-- E-bülten aboneliğinden çıkış: e-postadaki kişiye özel bağlantıdan gelinir; çıkış için onay istenir. --}}
<x-layouts.store :title="$settings['newsletter_unsub_title']" :noindex="true">
    <div class="container-page max-w-xl py-14 sm:py-20">
        <div class="card p-8 text-center sm:p-10">
            <span class="mx-auto flex size-14 items-center justify-center rounded-2xl {{ $done ? 'bg-success-soft text-success' : 'bg-accent-soft text-accent-ink' }}">
                <x-icon :name="$done ? 'check' : 'mail'" class="size-7" />
            </span>
            <h1 class="mt-5 text-2xl font-extrabold">{{ $settings['newsletter_unsub_title'] }}</h1>
            @if ($done)
                <p class="mt-3 text-muted">{{ $settings['newsletter_unsub_done'] }}</p>
                <a href="{{ route('home') }}" class="btn-ghost mt-6"><x-icon name="home" class="size-4" />Ana sayfa</a>
            @else
                <p class="mt-3 text-muted">{{ str_replace(':email', $subscriber->email, $settings['newsletter_unsub_text']) }}</p>
                <form method="post" action="{{ route('newsletter.unsubscribe.confirm', $subscriber->token) }}" class="mt-6 flex flex-wrap justify-center gap-3">
                    @csrf
                    <button class="btn-primary">{{ $settings['newsletter_unsub_button'] }}</button>
                    <a href="{{ route('home') }}" class="btn-ghost">Vazgeç</a>
                </form>
            @endif
        </div>
    </div>
</x-layouts.store>
