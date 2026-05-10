@props([
    'config' => null,
    'class' => '',
    'background' => 'transparent',
])

@php
    $cfg = \App\Support\Avatar::normalize($config);
    $skin = $cfg['skin'];
    $hairStyle = $cfg['hair']['style'];
    $hairColor = $cfg['hair']['color'];
    $topStyle = $cfg['top']['style'];
    $topColor = $cfg['top']['color'];
    $bottomStyle = $cfg['bottom']['style'];
    $bottomColor = $cfg['bottom']['color'];
    $shoeStyle = $cfg['shoes']['style'];
    $shoeColor = $cfg['shoes']['color'];
    $hatStyle = $cfg['hat']['style'];
    $hatColor = $cfg['hat']['color'];

    // A dress hides the bottom entirely.
    $hideBottom = $topStyle === 'dress';

    $body = \App\Support\Avatar::bodyScale($cfg['body_type'], $cfg['height']);
    // Scale around centerline x=100, y=110 (top of shoulders) so the head stays anchored.
    $bodyTransform = sprintf(
        'translate(%.2f %.2f) scale(%.3f %.3f)',
        100 - 100 * $body['scale_x'],
        110 - 110 * $body['scale_y'],
        $body['scale_x'],
        $body['scale_y'],
    );
@endphp

<svg viewBox="0 0 200 260" xmlns="http://www.w3.org/2000/svg" class="{{ $class }}" preserveAspectRatio="xMidYMid meet">
    <defs>
        <clipPath id="bodyClip-{{ $id = uniqid() }}">
            <rect x="0" y="0" width="200" height="260" />
        </clipPath>
        @if ($background === 'transparent')
            <pattern id="transparentGrid-{{ $id }}" patternUnits="userSpaceOnUse" width="16" height="16">
                <rect width="16" height="16" fill="#ffffff" />
                <rect width="8" height="8" fill="#d4d4d8" />
                <rect x="8" y="8" width="8" height="8" fill="#d4d4d8" />
            </pattern>
        @endif
        @if ($topStyle === 'hoodie')
            <pattern id="hoodLining-{{ $id }}" patternUnits="userSpaceOnUse" width="6" height="6" patternTransform="rotate(45)">
                <rect width="6" height="6" fill="{{ $topColor }}" />
                <line x1="0" y1="0" x2="0" y2="6" stroke="#3f3f46" stroke-width="1.5" />
            </pattern>
        @endif
    </defs>

    {{-- Background --}}
    @if ($background === 'transparent')
        <rect x="0" y="0" width="200" height="260" rx="16" fill="url(#transparentGrid-{{ $id }})" />
    @else
        <rect x="0" y="0" width="200" height="260" rx="16" fill="{{ $background }}" />
    @endif

    {{-- Hair (back layer) for long styles --}}
    @if ($hairStyle === 'long')
        <path d="M58 60 Q50 110 60 165 L75 175 L140 175 L140 165 Q150 110 142 60 Q138 28 100 26 Q62 28 58 60 Z"
              fill="{{ $hairColor }}" />
    @endif

    <g transform="{{ $bodyTransform }}">
    {{-- Neck (skin) --}}
    <rect x="92" y="92" width="16" height="22" fill="{{ $skin }}" />
    <path d="M92 110 Q100 116 108 110" fill="none" stroke="rgba(0,0,0,0.08)" stroke-width="1" />

    {{-- Legs (skin), drawn before pants/shorts/skirt so skin shows where exposed --}}
    @unless ($hideBottom)
        <rect x="76" y="180" width="20" height="64" rx="6" fill="{{ $skin }}" />
        <rect x="104" y="180" width="20" height="64" rx="6" fill="{{ $skin }}" />
    @endunless

    {{-- Bottom: pants / shorts / skirt --}}
    @if (! $hideBottom)
        @if ($bottomStyle === 'pants')
            <rect x="74" y="178" width="24" height="66" rx="6" fill="{{ $bottomColor }}" />
            <rect x="102" y="178" width="24" height="66" rx="6" fill="{{ $bottomColor }}" />
        @elseif ($bottomStyle === 'shorts')
            <rect x="74" y="178" width="24" height="32" rx="6" fill="{{ $bottomColor }}" />
            <rect x="102" y="178" width="24" height="32" rx="6" fill="{{ $bottomColor }}" />
        @elseif ($bottomStyle === 'skirt')
            <path d="M62 178 L138 178 L150 220 L50 220 Z" fill="{{ $bottomColor }}" />
        @endif
    @endif

    {{-- Shoes --}}
    @php
        $shoeY = $hideBottom ? 240 : 240;
    @endphp
    @if ($shoeStyle === 'sneakers')
        <ellipse cx="86" cy="246" rx="14" ry="8" fill="{{ $shoeColor }}" />
        <ellipse cx="114" cy="246" rx="14" ry="8" fill="{{ $shoeColor }}" />
        <rect x="72" y="244" width="28" height="3" fill="rgba(255,255,255,0.6)" />
        <rect x="100" y="244" width="28" height="3" fill="rgba(255,255,255,0.6)" />
    @elseif ($shoeStyle === 'boots')
        <rect x="72" y="228" width="28" height="22" rx="4" fill="{{ $shoeColor }}" />
        <rect x="100" y="228" width="28" height="22" rx="4" fill="{{ $shoeColor }}" />
        <ellipse cx="86" cy="250" rx="16" ry="5" fill="{{ $shoeColor }}" />
        <ellipse cx="114" cy="250" rx="16" ry="5" fill="{{ $shoeColor }}" />
    @elseif ($shoeStyle === 'flats')
        <ellipse cx="86" cy="248" rx="14" ry="5" fill="{{ $shoeColor }}" />
        <ellipse cx="114" cy="248" rx="14" ry="5" fill="{{ $shoeColor }}" />
    @elseif ($shoeStyle === 'sandals')
        {{-- sole --}}
        <ellipse cx="86" cy="250" rx="14" ry="3.5" fill="{{ $shoeColor }}" />
        <ellipse cx="114" cy="250" rx="14" ry="3.5" fill="{{ $shoeColor }}" />
        {{-- toes (skin) peeking out above the sole --}}
        @foreach ([78, 83, 88, 93] as $tx)
            <circle cx="{{ $tx }}" cy="245" r="2.2" fill="{{ $skin }}" />
        @endforeach
        @foreach ([106, 111, 116, 121] as $tx)
            <circle cx="{{ $tx }}" cy="245" r="2.2" fill="{{ $skin }}" />
        @endforeach
        {{-- straps across the top of the foot --}}
        <path d="M74 244 Q86 238 98 244" fill="none" stroke="{{ $shoeColor }}" stroke-width="2.5" stroke-linecap="round" />
        <path d="M102 244 Q114 238 126 244" fill="none" stroke="{{ $shoeColor }}" stroke-width="2.5" stroke-linecap="round" />
    @endif

    {{-- Top: t-shirt, dress shirt, hoodie, dress --}}
    @if ($topStyle === 'tshirt')
        {{-- short sleeves --}}
        <path d="M56 132 Q56 115 68 110 L78 106 L100 112 L122 106 L132 110 Q144 115 144 132 L126 145 L126 185 L74 185 L74 145 Z"
              fill="{{ $topColor }}" />
        {{-- neckline --}}
        <path d="M88 110 Q100 122 112 110" fill="{{ $skin }}" stroke="rgba(0,0,0,0.08)" stroke-width="1" />
    @elseif ($topStyle === 'dress-shirt')
        <path d="M56 132 Q56 115 70 110 L80 108 L100 118 L120 108 L130 110 Q144 115 144 132 L126 145 L126 200 L74 200 L74 145 Z"
              fill="{{ $topColor }}" />
        {{-- collar V --}}
        <path d="M88 108 L100 130 L112 108 Z" fill="{{ $skin }}" />
        <path d="M88 108 L100 130 L112 108" fill="none" stroke="rgba(0,0,0,0.15)" stroke-width="1.5" />
        {{-- buttons --}}
        <circle cx="100" cy="145" r="1.6" fill="rgba(0,0,0,0.25)" />
        <circle cx="100" cy="160" r="1.6" fill="rgba(0,0,0,0.25)" />
        <circle cx="100" cy="175" r="1.6" fill="rgba(0,0,0,0.25)" />
    @elseif ($topStyle === 'hoodie')
        {{-- hood lining (hatched) behind head, fills the inner gap beside the neck --}}
        <path d="M70 115 L70 105 Q70 80 100 75 Q130 80 130 105 L130 115 Z"
              fill="url(#hoodLining-{{ $id }})" />
        {{-- neck redrawn in front of hood lining --}}
        <rect x="92" y="92" width="16" height="22" fill="{{ $skin }}" />
        <path d="M92 110 Q100 116 108 110" fill="none" stroke="rgba(0,0,0,0.08)" stroke-width="1" />
        {{-- hood behind head --}}
        <path d="M55 105 Q55 60 100 55 Q145 60 145 105 L130 115 L130 105 Q130 80 100 75 Q70 80 70 105 L70 115 Z"
              fill="{{ $topColor }}" />
        <path d="M56 134 Q56 115 68 110 L78 108 L100 118 L122 108 L132 110 Q144 115 144 134 L126 148 L126 200 L74 200 L74 148 Z"
              fill="{{ $topColor }}" />
        {{-- pocket --}}
        <path d="M78 165 L122 165 L118 185 L82 185 Z" fill="rgba(0,0,0,0.12)" />
        {{-- drawstrings --}}
        <line x1="92" y1="115" x2="92" y2="140" stroke="rgba(255,255,255,0.7)" stroke-width="1.5" />
        <line x1="108" y1="115" x2="108" y2="140" stroke="rgba(255,255,255,0.7)" stroke-width="1.5" />
    @elseif ($topStyle === 'dress')
        <path d="M56 132 Q56 115 68 110 L78 106 L100 112 L122 106 L132 110 Q144 115 144 132 L126 145 L160 240 L40 240 L74 145 Z"
              fill="{{ $topColor }}" />
        <path d="M88 110 Q100 122 112 110" fill="{{ $skin }}" stroke="rgba(0,0,0,0.08)" stroke-width="1" />
        {{-- waist accent --}}
        <rect x="74" y="165" width="52" height="4" fill="rgba(0,0,0,0.18)" />
    @endif

    {{-- Arms --}}
    @php $longSleeve = in_array($topStyle, ['dress-shirt', 'hoodie'], true); @endphp
    @if ($longSleeve)
        {{-- Long sleeve tubes --}}
        <rect x="58" y="135" width="14" height="65" rx="6" fill="{{ $topColor }}" />
        <rect x="128" y="135" width="14" height="65" rx="6" fill="{{ $topColor }}" />
        {{-- Hands emerging from long sleeves --}}
        <circle cx="65" cy="204" r="7" fill="{{ $skin }}" />
        <circle cx="135" cy="204" r="7" fill="{{ $skin }}" />
        <path d="M59 200 Q65 198 71 200" fill="none" stroke="rgba(0,0,0,0.12)" stroke-width="1" />
        <path d="M129 200 Q135 198 141 200" fill="none" stroke="rgba(0,0,0,0.12)" stroke-width="1" />
    @else
        {{-- Skin forearms hanging from short sleeves, ending in hands --}}
        <rect x="58" y="142" width="14" height="56" rx="6" fill="{{ $skin }}" />
        <rect x="128" y="142" width="14" height="56" rx="6" fill="{{ $skin }}" />
        <circle cx="65" cy="202" r="7" fill="{{ $skin }}" />
        <circle cx="135" cy="202" r="7" fill="{{ $skin }}" />
    @endif
    </g>

    {{-- Head --}}
    <circle cx="100" cy="64" r="34" fill="{{ $skin }}" />
    {{-- Ears --}}
    @if ($cfg['ears'] === 'small')
        <ellipse cx="67" cy="68" rx="3" ry="6" fill="{{ $skin }}" />
        <ellipse cx="133" cy="68" rx="3" ry="6" fill="{{ $skin }}" />
    @elseif ($cfg['ears'] === 'large')
        <ellipse cx="64" cy="68" rx="7" ry="11" fill="{{ $skin }}" />
        <ellipse cx="136" cy="68" rx="7" ry="11" fill="{{ $skin }}" />
    @elseif ($cfg['ears'] === 'pointed')
        <path d="M70 60 L62 52 L66 76 Q70 78 72 72 Z" fill="{{ $skin }}" />
        <path d="M130 60 L138 52 L134 76 Q130 78 128 72 Z" fill="{{ $skin }}" />
    @else
        <ellipse cx="66" cy="68" rx="5" ry="8" fill="{{ $skin }}" />
        <ellipse cx="134" cy="68" rx="5" ry="8" fill="{{ $skin }}" />
    @endif

    {{-- Eyes --}}
    @php $eyeColor = $cfg['eye_color']; @endphp
    @if ($cfg['eyes'] === 'default')
        <ellipse cx="86" cy="64" rx="5" ry="4" fill="#ffffff" />
        <ellipse cx="114" cy="64" rx="5" ry="4" fill="#ffffff" />
        <circle cx="86" cy="64" r="3" fill="{{ $eyeColor }}" />
        <circle cx="114" cy="64" r="3" fill="{{ $eyeColor }}" />
    @elseif ($cfg['eyes'] === 'happy')
        <path d="M81 66 Q86 60 91 66" fill="none" stroke="{{ $eyeColor }}" stroke-width="2.2" stroke-linecap="round" />
        <path d="M109 66 Q114 60 119 66" fill="none" stroke="{{ $eyeColor }}" stroke-width="2.2" stroke-linecap="round" />
    @elseif ($cfg['eyes'] === 'wink')
        <ellipse cx="86" cy="64" rx="5" ry="4" fill="#ffffff" />
        <circle cx="86" cy="64" r="3" fill="{{ $eyeColor }}" />
        <path d="M109 64 Q114 60 119 64" fill="none" stroke="{{ $eyeColor }}" stroke-width="2.2" stroke-linecap="round" />
    @endif

    {{-- Nose --}}
    @if ($cfg['nose'] === 'button')
        <ellipse cx="100" cy="74" rx="3" ry="2" fill="rgba(0,0,0,0.18)" />
    @elseif ($cfg['nose'] === 'pointed')
        <path d="M100 68 L97 76 Q100 78 103 76 Z" fill="none" stroke="rgba(0,0,0,0.32)" stroke-width="1.4" stroke-linejoin="round" stroke-linecap="round" />
    @elseif ($cfg['nose'] === 'wide')
        <path d="M95 74 Q100 77 105 74" fill="none" stroke="rgba(0,0,0,0.22)" stroke-width="1.2" stroke-linecap="round" />
        <circle cx="96" cy="74" r="1.3" fill="rgba(0,0,0,0.32)" />
        <circle cx="104" cy="74" r="1.3" fill="rgba(0,0,0,0.32)" />
    @endif

    {{-- Mouth --}}
    @php $mouthColor = $cfg['mouth_color']; @endphp
    @if ($cfg['mouth'] === 'smile')
        <path d="M88 80 Q100 90 112 80" fill="none" stroke="{{ $mouthColor }}" stroke-width="2" stroke-linecap="round" />
    @elseif ($cfg['mouth'] === 'neutral')
        <line x1="90" y1="82" x2="110" y2="82" stroke="{{ $mouthColor }}" stroke-width="2" stroke-linecap="round" />
    @elseif ($cfg['mouth'] === 'grin')
        <path d="M86 78 Q100 92 114 78 Q100 86 86 78 Z" fill="{{ $mouthColor }}" stroke="#3a1f14" stroke-width="1.5" />
        <path d="M88 80 L112 80" stroke="#ffffff" stroke-width="1.2" />
    @endif

    {{-- Facial hair --}}
    @php $facialHairColor = $cfg['facial_hair_color']; @endphp
    @if ($cfg['facial_hair'] === 'mustache')
        <path d="M86 76 Q92 82 100 78 Q108 82 114 76 Q108 80 100 80 Q92 80 86 76 Z" fill="{{ $facialHairColor }}" />
    @elseif ($cfg['facial_hair'] === 'goatee')
        {{-- mustache --}}
        <path d="M86 76 Q92 82 100 78 Q108 82 114 76 Q108 80 100 80 Q92 80 86 76 Z" fill="{{ $facialHairColor }}" />
        {{-- soul patch --}}
        <path d="M97 84 L103 84 L102 88 L98 88 Z" fill="{{ $facialHairColor }}" />
        {{-- chin tuft --}}
        <path d="M92 90 Q100 94 108 90 Q106 100 100 100 Q94 100 92 90 Z" fill="{{ $facialHairColor }}" />
    @elseif ($cfg['facial_hair'] === 'beard')
        <path d="M70 72 Q72 95 100 100 Q128 95 130 72 Q120 88 100 88 Q80 88 70 72 Z" fill="{{ $facialHairColor }}" />
        <path d="M86 76 Q92 82 100 78 Q108 82 114 76 Q108 80 100 80 Q92 80 86 76 Z" fill="{{ $facialHairColor }}" />
    @endif

    {{-- Hair (front layers) --}}
    @if ($hairStyle === 'short')
        <path d="M68 56 Q70 30 100 28 Q130 30 132 56 Q130 42 100 40 Q70 42 68 56 Z" fill="{{ $hairColor }}" />
        <path d="M68 56 Q72 48 90 46 L90 38 Q75 42 68 56 Z" fill="{{ $hairColor }}" />
    @elseif ($hairStyle === 'buzz')
        {{-- solid base covering the scalp --}}
        <path d="M68 52 Q70 38 100 36 Q130 38 132 52 Q120 46 100 46 Q80 46 68 52 Z" fill="{{ $hairColor }}" />
        {{-- spiky peaks across the top --}}
        <path d="M68 50 L74 30 L82 50 L90 24 L100 50 L110 22 L118 50 L126 28 L132 50 Z"
              fill="{{ $hairColor }}" />
    @elseif ($hairStyle === 'bun')
        <circle cx="100" cy="22" r="14" fill="{{ $hairColor }}" />
        <path d="M68 56 Q70 32 100 30 Q130 32 132 56 Q130 44 100 42 Q70 44 68 56 Z" fill="{{ $hairColor }}" />
    @elseif ($hairStyle === 'long')
        {{-- front fringe (back already drawn) --}}
        <path d="M66 58 Q70 28 100 26 Q130 28 134 58 Q128 42 100 40 Q72 42 66 58 Z" fill="{{ $hairColor }}" />
    @endif

    {{-- Hat --}}
    @if ($hatStyle === 'cap')
        <path d="M64 42 Q66 22 100 20 Q134 22 136 42 L100 38 Z" fill="{{ $hatColor }}" />
        <path d="M64 42 L150 42 L148 50 L66 50 Z" fill="{{ $hatColor }}" />
    @elseif ($hatStyle === 'beanie')
        <path d="M58 50 Q60 18 100 16 Q140 18 142 50 Z" fill="{{ $hatColor }}" />
        <rect x="56" y="46" width="88" height="9" rx="3" fill="{{ $hatColor }}" stroke="rgba(0,0,0,0.2)" />
        <circle cx="100" cy="14" r="6" fill="{{ $hatColor }}" stroke="rgba(0,0,0,0.2)" />
    @elseif ($hatStyle === 'tophat')
        <rect x="74" y="2" width="52" height="38" fill="{{ $hatColor }}" />
        <rect x="58" y="36" width="84" height="8" rx="2" fill="{{ $hatColor }}" />
        <rect x="74" y="28" width="52" height="6" fill="rgba(255,255,255,0.15)" />
    @endif
</svg>
