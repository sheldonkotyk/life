<?php

namespace App\Livewire;

use App\Support\Avatar;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class AvatarBuilder extends Component
{
    public array $config = [];

    public function mount(): void
    {
        $this->config = Avatar::normalize(auth()->user()->avatar_config);
    }

    public function setSkin(string $color): void
    {
        $this->config['skin'] = $color;
    }

    public function setHairStyle(string $style): void
    {
        $this->config['hair']['style'] = $style;
    }

    public function setHairColor(string $color): void
    {
        $this->config['hair']['color'] = $color;
    }

    public function setEyes(string $style): void
    {
        $this->config['eyes'] = $style;
    }

    public function setEyeColor(string $color): void
    {
        $this->config['eye_color'] = $color;
    }

    public function setMouth(string $style): void
    {
        $this->config['mouth'] = $style;
    }

    public function setFacialHair(string $style): void
    {
        $this->config['facial_hair'] = $style;
    }

    public function setFacialHairColor(string $color): void
    {
        $this->config['facial_hair_color'] = $color;
    }

    public function setTopStyle(string $style): void
    {
        $this->config['top']['style'] = $style;
    }

    public function setTopColor(string $color): void
    {
        $this->config['top']['color'] = $color;
    }

    public function setBottomStyle(string $style): void
    {
        $this->config['bottom']['style'] = $style;
    }

    public function setBottomColor(string $color): void
    {
        $this->config['bottom']['color'] = $color;
    }

    public function setShoeStyle(string $style): void
    {
        $this->config['shoes']['style'] = $style;
    }

    public function setShoeColor(string $color): void
    {
        $this->config['shoes']['color'] = $color;
    }

    public function setHatStyle(string $style): void
    {
        $this->config['hat']['style'] = $style;
    }

    public function setHatColor(string $color): void
    {
        $this->config['hat']['color'] = $color;
    }

    public function randomize(): void
    {
        $this->config = [
            'skin' => collect(Avatar::SKIN_TONES)->random(),
            'hair' => [
                'style' => collect(Avatar::HAIR_STYLES)->random(),
                'color' => collect(Avatar::HAIR_COLORS)->random(),
            ],
            'eyes' => collect(Avatar::EYE_STYLES)->random(),
            'eye_color' => collect(Avatar::EYE_COLORS)->random(),
            'mouth' => collect(Avatar::MOUTH_STYLES)->random(),
            'facial_hair' => collect(Avatar::FACIAL_HAIR_STYLES)->random(),
            'facial_hair_color' => collect(Avatar::FACIAL_HAIR_COLORS)->random(),
            'top' => [
                'style' => collect(Avatar::TOP_STYLES)->random(),
                'color' => collect(Avatar::TOP_COLORS)->random(),
            ],
            'bottom' => [
                'style' => collect(Avatar::BOTTOM_STYLES)->random(),
                'color' => collect(Avatar::BOTTOM_COLORS)->random(),
            ],
            'shoes' => [
                'style' => collect(Avatar::SHOE_STYLES)->random(),
                'color' => collect(Avatar::SHOE_COLORS)->random(),
            ],
            'hat' => [
                'style' => collect(Avatar::HAT_STYLES)->random(),
                'color' => collect(Avatar::HAT_COLORS)->random(),
            ],
        ];
    }

    public function save(): void
    {
        $user = auth()->user();
        $user->update(['avatar_config' => Avatar::normalize($this->config)]);

        // If switching to built avatar, drop the uploaded image so it doesn't shadow the SVG.
        $existing = $user->getRawOriginal('avatar');
        if ($existing && ! str_starts_with($existing, 'http')) {
            Storage::disk('public')->delete($existing);
            $user->update(['avatar' => null]);
        }

        session()->flash('status', 'Avatar saved.');
    }

    public function clearBuiltAvatar(): void
    {
        auth()->user()->update(['avatar_config' => null]);
        $this->config = Avatar::defaultConfig();
        session()->flash('status', 'Built avatar removed.');
    }

    public function render()
    {
        return view('livewire.avatar-builder', [
            'opts' => [
                'skinTones' => Avatar::SKIN_TONES,
                'hairColors' => Avatar::HAIR_COLORS,
                'topColors' => Avatar::TOP_COLORS,
                'bottomColors' => Avatar::BOTTOM_COLORS,
                'shoeColors' => Avatar::SHOE_COLORS,
                'hatColors' => Avatar::HAT_COLORS,
                'hairStyles' => Avatar::HAIR_STYLES,
                'eyeStyles' => Avatar::EYE_STYLES,
                'eyeColors' => Avatar::EYE_COLORS,
                'mouthStyles' => Avatar::MOUTH_STYLES,
                'facialHairStyles' => Avatar::FACIAL_HAIR_STYLES,
                'facialHairColors' => Avatar::FACIAL_HAIR_COLORS,
                'topStyles' => Avatar::TOP_STYLES,
                'bottomStyles' => Avatar::BOTTOM_STYLES,
                'shoeStyles' => Avatar::SHOE_STYLES,
                'hatStyles' => Avatar::HAT_STYLES,
            ],
        ]);
    }
}
