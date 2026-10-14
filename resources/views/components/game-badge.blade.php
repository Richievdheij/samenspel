@props(['game'])

<span {{ $attributes->class(['game-badge']) }}>
    <span class="game-badge__logo" aria-hidden="true"></span>
    <span class="game-badge__name">{{ $game->name }}</span>
</span>
