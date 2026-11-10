@if (session('status'))
    <div x-data="flash" x-show="shown" x-transition.opacity role="status"
         {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-xl border border-success/20 bg-success-soft px-4 py-3 text-sm text-success']) }}>
        <x-icon name="check" class="mt-0.5 size-4 shrink-0" />
        <p class="flex-1 font-medium">{{ session('status') }}</p>
        <button type="button" x-on:click="dismiss" class="text-success/70 hover:text-success" aria-label="Kapat"><x-icon name="x" class="size-4" /></button>
    </div>
@endif
@if ($errors->hasAny(['admin', 'role']))
    <div {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-xl border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger']) }} role="alert">
        <x-icon name="alert" class="mt-0.5 size-4 shrink-0" />
        <p class="font-medium">{{ $errors->first('admin') ?: $errors->first('role') }}</p>
    </div>
@endif
