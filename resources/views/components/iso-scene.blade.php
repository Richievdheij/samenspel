{{--
    <x-iso-scene>, the home page illustration: a game table in isometric line
    art, with a screen, two dice and a pawn. Every corner lies on a 30° grid; the
    shapes were projected from 3D boxes, not drawn by eye, so they stay true.

        <x-iso-scene class="home__scene" />

    Decorative: hidden from screen readers, because the heading beside it says
    what the page is about. Colours come from the tokens through the classes in
    resources/scss/components/_iso-scene.scss, so the scene follows the palette.
--}}
<svg {{ $attributes->class(['iso-scene']) }} viewBox="125 66 312 262" aria-hidden="true" focusable="false">
    <path class="iso__left" d="M135.3 222.0 L301.6 318.0 L301.6 303.6 L135.3 207.6 Z" />
    <path class="iso__right" d="M426.3 246.0 L301.6 318.0 L301.6 303.6 L426.3 231.6 Z" />
    <path class="iso__top" d="M260.0 135.6 L426.3 231.6 L301.6 303.6 L135.3 207.6 Z" />
    <path class="iso__left-dark" d="M276.6 188.4 L297.4 200.4 L297.4 193.2 L276.6 181.2 Z" />
    <path class="iso__right-dark" d="M314.0 190.8 L297.4 200.4 L297.4 193.2 L314.0 183.6 Z" />
    <path class="iso__top-dark" d="M293.3 171.6 L314.0 183.6 L297.4 193.2 L276.6 181.2 Z" />
    <path class="iso__left-dark" d="M287.0 182.4 L295.3 187.2 L295.3 165.6 L287.0 160.8 Z" />
    <path class="iso__right-dark" d="M303.6 182.4 L295.3 187.2 L295.3 165.6 L303.6 160.8 Z" />
    <path class="iso__top-dark" d="M295.3 156.0 L303.6 160.8 L295.3 165.6 L287.0 160.8 Z" />
    <path class="iso__left-dark" d="M254.8 143.4 L337.9 191.4 L337.9 129.0 L254.8 81.0 Z" />
    <path class="iso__right-dark" d="M345.2 187.2 L337.9 191.4 L337.9 129.0 L345.2 124.8 Z" />
    <path class="iso__top-dark" d="M262.1 76.8 L345.2 124.8 L337.9 129.0 L254.8 81.0 Z" />
    <path class="iso__screen" d="M260.0 140.4 L332.7 182.4 L332.7 132.0 L260.0 90.0 Z" />
    <path class="iso__squiggle" transform="matrix(20.785 12.000 0.000 -24.000 229.9 167.4)" d="M1.8 2.9 C2.2 3.5 2.6 3.5 3.0 2.9 S3.8 2.3 4.2 2.9 S4.6 3.3 4.7 3.1" />
    <path class="iso__die-left" d="M257.9 247.2 L293.3 267.6 L293.3 226.8 L257.9 206.4 Z" />
    <path class="iso__die-right" d="M328.6 247.2 L293.3 267.6 L293.3 226.8 L328.6 206.4 Z" />
    <path class="iso__die-top" d="M293.3 186.0 L328.6 206.4 L293.3 226.8 L257.9 206.4 Z" />
    <circle class="iso__pip" transform="matrix(20.785 12.000 -20.785 12.000 260.0 94.8)" cx="5.45" cy="3.85" r="0.17" />
    <circle class="iso__pip" transform="matrix(-20.785 12.000 0.000 -24.000 390.9 225.6)" cx="3.45" cy="1.85" r="0.17" /><circle class="iso__pip" transform="matrix(-20.785 12.000 0.000 -24.000 390.9 225.6)" cx="4.25" cy="1.05" r="0.17" />
    <circle class="iso__pip" transform="matrix(20.785 12.000 0.000 -24.000 162.3 206.4)" cx="5.00" cy="1.90" r="0.17" /><circle class="iso__pip" transform="matrix(20.785 12.000 0.000 -24.000 162.3 206.4)" cx="5.45" cy="1.45" r="0.17" /><circle class="iso__pip" transform="matrix(20.785 12.000 0.000 -24.000 162.3 206.4)" cx="5.90" cy="1.00" r="0.17" />
    <path class="iso__accent-left" d="M191.4 208.8 L214.3 222.0 L214.3 195.6 L191.4 182.4 Z" />
    <path class="iso__accent-right" d="M237.1 208.8 L214.3 222.0 L214.3 195.6 L237.1 182.4 Z" />
    <path class="iso__accent-top" d="M214.3 169.2 L237.1 182.4 L214.3 195.6 L191.4 182.4 Z" />
    <circle class="iso__pip" transform="matrix(20.785 12.000 -20.785 12.000 260.0 109.2)" cx="1.70" cy="3.90" r="0.13" /><circle class="iso__pip" transform="matrix(20.785 12.000 -20.785 12.000 260.0 109.2)" cx="2.20" cy="4.40" r="0.13" />
    <circle class="iso__pip" transform="matrix(20.785 12.000 0.000 -24.000 162.3 206.4)" cx="1.70" cy="1.40" r="0.13" /><circle class="iso__pip" transform="matrix(20.785 12.000 0.000 -24.000 162.3 206.4)" cx="1.95" cy="1.15" r="0.13" /><circle class="iso__pip" transform="matrix(20.785 12.000 0.000 -24.000 162.3 206.4)" cx="2.20" cy="0.90" r="0.13" />
    <circle class="iso__pip" transform="matrix(-20.785 12.000 0.000 -24.000 312.0 180.0)" cx="4.15" cy="1.15" r="0.13" />
    <path class="iso__sun-left" d="M363.9 234.0 L378.5 242.4 L378.5 213.6 L363.9 205.2 Z" />
    <path class="iso__sun-right" d="M393.0 234.0 L378.5 242.4 L378.5 213.6 L393.0 205.2 Z" />
    <path class="iso__sun-top" d="M378.5 196.8 L393.0 205.2 L378.5 213.6 L363.9 205.2 Z" />
</svg>
