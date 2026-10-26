@component('layouts.app', ['title' => $heading.' | Trattoria Bella Vita'])
    <div class="mx-auto flex min-h-[70vh] max-w-md flex-col items-center justify-center px-6 py-16 text-center">
        <p class="font-display text-7xl font-bold text-tomato" aria-hidden="true">{{ $code }}</p>
        <h1 class="font-display mt-4 text-3xl font-bold">{{ $heading }}</h1>
        <p class="mt-3 text-ink/75">{{ $message }}</p>

        <div class="mt-8 flex flex-wrap justify-center gap-3">
            @isset($retry)
                <a href="javascript:location.reload()" class="inline-flex min-h-11 items-center rounded-full bg-tomato px-6 font-bold text-white shadow hover:bg-tomato-dark">{{ __('Try again') }}</a>
            @endisset
            <a href="{{ url('/') }}" class="inline-flex min-h-11 items-center rounded-full border border-basil px-6 font-bold text-basil-dark hover:bg-basil hover:text-white">{{ __('Back to the menu') }}</a>
        </div>
    </div>
@endcomponent
