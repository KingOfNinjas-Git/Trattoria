@props(['item', 'eager' => false, 'thumb' => false])
{{-- Dish photo with a shimmer behind it while it loads; dishes without a photo get an illustrated plate.
     `thumb` uses the small copy of the photo (lists and the cart); the dish popup shows the full picture. --}}
@if ($item->image_url)
    <div {{ $attributes->merge(['class' => 'shimmer relative overflow-hidden']) }}>
        <img src="{{ $thumb ? $item->thumb_url : $item->image_url }}" alt="{{ $item->name }}" @if (! $eager) loading="lazy" @endif
             class="h-full w-full object-cover">
    </div>
@else
    <div {{ $attributes->merge(['class' => 'tablecloth relative flex items-center justify-center overflow-hidden']) }} role="img" aria-label="{{ $item->name }}">
        <svg viewBox="0 0 64 64" class="h-1/2 w-1/2 text-tomato/70" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="32" cy="32" r="16" fill="rgba(255,255,255,.85)"/>
            <circle cx="32" cy="32" r="10"/>
            <path d="M8 10v14a4 4 0 0 0 4 4v26M12 10v10M16 10v14a4 4 0 0 1-4 4"/>
            <path d="M56 10c-4 2-6 8-6 14 0 3 2 4 4 4v26"/>
        </svg>
    </div>
@endif
