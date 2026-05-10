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
    $hatStyle = $cfg['hat']['style'];
    $hatColor = $cfg['hat']['color'];

    $body = \App\Support\Avatar::bodyScale($cfg['body_type']);
    $bodyTransform = sprintf(
        'translate(%.2f %.2f) scale(%.3f %.3f)',
        100 - 100 * $body['scale_x'],
        110 - 110 * $body['scale_y'],
        $body['scale_x'],
        $body['scale_y'],
    );
@endphp

{{-- Head/shoulders crop. Coordinates align with the full-body SVG so glyphs match. --}}
<svg viewBox="40 0 120 130" xmlns="http://www.w3.org/2000/svg" class="{{ $class }}" preserveAspectRatio="xMidYMid meet">
    {{-- Background --}}
    <rect x="40" y="0" width="120" height="130" fill="{{ $background }}" />

    {{-- Hair (back layer) for long styles --}}
    @if ($hairStyle === 'long')
        <path d="M58 60 Q50 110 60 165 L75 175 L75 95 Q75 60 100 50 Q125 60 125 95 L125 175 L140 165 Q150 110 142 60 Z"
              fill="{{ $hairColor }}" />
    @endif

    <g transform="{{ $bodyTransform }}">
    {{-- Neck --}}
    <rect x="92" y="92" width="16" height="22" fill="{{ $skin }}" />
    <path d="M92 110 Q100 116 108 110" fill="none" stroke="rgba(0,0,0,0.08)" stroke-width="1" />

    {{-- Shoulders / top (cropped) --}}
    @if ($topStyle === 'tshirt')
        <path d="M40 130 Q40 115 60 110 L75 105 L100 112 L125 105 L140 110 Q160 115 160 130 L155 145 L140 140 L140 185 L60 185 L60 140 L45 145 Z"
              fill="{{ $topColor }}" />
        <path d="M88 110 Q100 122 112 110" fill="{{ $skin }}" stroke="rgba(0,0,0,0.08)" stroke-width="1" />
    @elseif ($topStyle === 'dress-shirt')
        <path d="M50 135 Q50 115 70 110 L80 108 L100 118 L120 108 L130 110 Q150 115 150 135 L150 200 L132 200 L132 195 L68 195 L68 200 L50 200 Z"
              fill="{{ $topColor }}" />
        <path d="M88 108 L100 130 L112 108 Z" fill="{{ $skin }}" />
        <path d="M88 108 L100 130 L112 108" fill="none" stroke="rgba(0,0,0,0.15)" stroke-width="1.5" />
    @elseif ($topStyle === 'hoodie')
        <path d="M55 105 Q55 60 100 55 Q145 60 145 105 L130 115 L130 105 Q130 80 100 75 Q70 80 70 105 L70 115 Z"
              fill="{{ $topColor }}" />
        <path d="M40 135 Q40 115 60 110 L75 108 L100 118 L125 108 L140 110 Q160 115 160 135 L160 200 L40 200 Z"
              fill="{{ $topColor }}" />
        <line x1="92" y1="115" x2="92" y2="140" stroke="rgba(255,255,255,0.7)" stroke-width="1.5" />
        <line x1="108" y1="115" x2="108" y2="140" stroke="rgba(255,255,255,0.7)" stroke-width="1.5" />
    @elseif ($topStyle === 'dress')
        <path d="M40 130 Q40 115 60 110 L75 105 L100 112 L125 105 L140 110 Q160 115 160 130 L155 145 L140 140 L160 240 L40 240 L60 140 L45 145 Z"
              fill="{{ $topColor }}" />
        <path d="M88 110 Q100 122 112 110" fill="{{ $skin }}" stroke="rgba(0,0,0,0.08)" stroke-width="1" />
    @endif
    </g>

    {{-- Head --}}
    <circle cx="100" cy="64" r="34" fill="{{ $skin }}" />
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
        <circle cx="86" cy="64" r="3" fill="{{ $eyeColor }}" />
        <circle cx="114" cy="64" r="3" fill="{{ $eyeColor }}" />
    @elseif ($cfg['eyes'] === 'happy')
        <path d="M81 66 Q86 60 91 66" fill="none" stroke="{{ $eyeColor }}" stroke-width="2.2" stroke-linecap="round" />
        <path d="M109 66 Q114 60 119 66" fill="none" stroke="{{ $eyeColor }}" stroke-width="2.2" stroke-linecap="round" />
    @elseif ($cfg['eyes'] === 'wink')
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
    @elseif ($cfg['facial_hair'] === 'beard')
        <path d="M70 72 Q72 95 100 100 Q128 95 130 72 Q120 88 100 88 Q80 88 70 72 Z" fill="{{ $facialHairColor }}" />
        <path d="M86 76 Q92 82 100 78 Q108 82 114 76 Q108 80 100 80 Q92 80 86 76 Z" fill="{{ $facialHairColor }}" />
    @endif

    {{-- Hair (front layers) --}}
    @if ($hairStyle === 'short')
        <path d="M68 56 Q70 30 100 28 Q130 30 132 56 Q130 42 100 40 Q70 42 68 56 Z" fill="{{ $hairColor }}" />
        <path d="M68 56 Q72 48 90 46 L90 38 Q75 42 68 56 Z" fill="{{ $hairColor }}" />
    @elseif ($hairStyle === 'buzz')
        <path d="M70 50 Q72 38 100 36 Q128 38 130 50 Q120 44 100 44 Q80 44 70 50 Z" fill="{{ $hairColor }}" opacity="0.85" />
    @elseif ($hairStyle === 'bun')
        <circle cx="100" cy="22" r="14" fill="{{ $hairColor }}" />
        <path d="M68 56 Q70 32 100 30 Q130 32 132 56 Q130 44 100 42 Q70 44 68 56 Z" fill="{{ $hairColor }}" />
    @elseif ($hairStyle === 'long')
        <path d="M66 58 Q70 28 100 26 Q130 28 134 58 Q128 42 100 40 Q72 42 66 58 Z" fill="{{ $hairColor }}" />
    @endif

    {{-- Hat --}}
    @if ($hatStyle === 'cap')
        <path d="M64 42 Q66 22 100 20 Q134 22 136 42 L100 38 Z" fill="{{ $hatColor }}" />
        <path d="M64 42 L150 42 L148 50 L66 50 Z" fill="{{ $hatColor }}" />
    @elseif ($hatStyle === 'beanie')
        <path d="M64 44 Q66 14 100 12 Q134 14 136 44 Z" fill="{{ $hatColor }}" />
        <rect x="62" y="40" width="76" height="8" rx="3" fill="{{ $hatColor }}" stroke="rgba(0,0,0,0.2)" />
        <circle cx="100" cy="10" r="6" fill="{{ $hatColor }}" stroke="rgba(0,0,0,0.2)" />
    @elseif ($hatStyle === 'tophat')
        <rect x="74" y="2" width="52" height="38" fill="{{ $hatColor }}" />
        <rect x="58" y="36" width="84" height="8" rx="2" fill="{{ $hatColor }}" />
        <rect x="74" y="28" width="52" height="6" fill="rgba(255,255,255,0.15)" />
    @endif
</svg>
