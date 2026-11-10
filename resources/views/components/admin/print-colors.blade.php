@props(['settings'])
{{-- Siyah-beyaz / renkli baskı hızlı aç-kapat düğmeleri (yazıcı arızası). En az biri açık kalır. --}}
<section class="card p-6">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h2 class="font-bold">Baskı türleri</h2>
            <p class="mt-1 text-sm text-muted">Yazıcı arızalandığında ilgili baskıyı tek tıkla kapatın; müşteriler yalnızca açık olanı görür.</p>
        </div>
    </div>
    @error('color')<p class="mt-3 rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger" role="alert">{{ $message }}</p>@enderror
    <div class="mt-4 grid gap-3 sm:grid-cols-2">
        @foreach (\App\Domain\Print\PrintOptions::COLORS as $key => $label)
            @php($on = (bool) $settings[$key.'_enabled'])
            <form method="post" action="{{ route('admin.print.color', $key) }}">
                @csrf
                <button type="submit" @class([
                    'flex w-full items-center gap-4 rounded-2xl border-2 p-4 text-left transition',
                    'border-success bg-success-soft hover:brightness-95' => $on,
                    'border-line bg-surface opacity-80 hover:opacity-100' => ! $on,
                ]) aria-pressed="{{ $on ? 'true' : 'false' }}">
                    <span @class(['grid size-12 shrink-0 place-items-center rounded-xl text-lg font-black',
                        'bg-ink text-white' => $key === 'bw',
                        'bg-linear-to-br from-sky-500 via-fuchsia-500 to-amber-400 text-white' => $key === 'color'])>{{ $key === 'bw' ? 'S/B' : 'R' }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-bold">{{ $label }} baskı</span>
                        <span @class(['mt-0.5 block text-sm font-semibold', 'text-success' => $on, 'text-muted' => ! $on])>{{ $on ? 'Aktif — vitrinde görünüyor' : 'Pasif — vitrinde gizli' }}</span>
                    </span>
                    <span @class(['relative h-7 w-12 shrink-0 rounded-full transition', 'bg-success' => $on, 'bg-line' => ! $on])>
                        <span @class(['absolute top-1 size-5 rounded-full bg-white shadow transition-all', 'left-6' => $on, 'left-1' => ! $on])></span>
                    </span>
                    <span class="sr-only">{{ $on ? 'Pasif yap' : 'Aktif yap' }}</span>
                </button>
            </form>
        @endforeach
    </div>
</section>
