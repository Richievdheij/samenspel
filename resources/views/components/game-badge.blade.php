@props(['game', 'compact' => false])

<span {{ $attributes->class(['game-badge', 'game-badge--compact' => $compact]) }} @if ($compact) aria-label="{{ $game->name }}" title="{{ $game->name }}" @endif>
    <span class="game-badge__logo" aria-hidden="true">
        @if ($game->cover_image_path || $game->logo_image_path || $game->cover_image_url)
            <img src="{{ $game->cover_image_path ? Storage::disk('public')->url($game->cover_image_path) : ($game->logo_image_path ? Storage::disk('public')->url($game->logo_image_path) : $game->cover_image_url) }}" alt="" loading="lazy" decoding="async">
        @else
            {{ mb_strtoupper(mb_substr($game->name, 0, 2)) }}
        @endif
    </span>
    @unless ($compact)
        <span class="game-badge__name">{{ $game->name }}</span>
    @endunless
</span>
