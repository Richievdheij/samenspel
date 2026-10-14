@props(['game', 'compact' => false])

<span {{ $attributes->class(['game-badge', 'game-badge--compact' => $compact]) }} @if ($compact) aria-label="{{ $game->name }}" title="{{ $game->name }}" @endif>
    <span class="game-badge__logo" aria-hidden="true">
        @if ($game->logo_image_path)
            <img src="{{ Storage::disk('public')->url($game->logo_image_path) }}" alt="" loading="lazy" decoding="async">
        @elseif ($compact)
            {{ mb_strtoupper(mb_substr($game->name, 0, 2)) }}
        @endif
    </span>
    @unless ($compact)
        <span class="game-badge__name">{{ $game->name }}</span>
    @endunless
</span>
