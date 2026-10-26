@component('layouts.app', ['title' => 'Photo credits'])
<div class="mx-auto max-w-2xl px-4 py-10">
    <h1 class="font-display text-3xl font-bold text-tomato">Photo credits</h1>
    <p class="mt-2 text-sm text-ink/70">
        Dish photos are from <a class="underline" href="https://commons.wikimedia.org" target="_blank" rel="noopener">Wikimedia Commons</a>
        and used under their respective Creative Commons licences. Thank you to the photographers.
    </p>

    <ul class="mt-6 space-y-3 text-sm">
        @foreach ($credits as $slug => $c)
            <li class="break-words rounded-lg bg-white p-3 ring-1 ring-ink/5">
                <p class="font-bold">{{ str_replace('File:', '', $c['title']) }}</p>
                <p class="text-ink/70">{{ $c['artist'] ?: 'Unknown author' }} · {{ $c['license'] }}</p>
                <a class="text-basil-dark underline" target="_blank" rel="noopener"
                   href="https://commons.wikimedia.org/wiki/{{ str_replace(' ', '_', $c['title']) }}">Source</a>
            </li>
        @endforeach
    </ul>

    <a href="{{ url('/') }}" class="mt-8 inline-block font-bold text-basil-dark underline">← Back to the menu</a>
</div>
@endcomponent
