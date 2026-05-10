<?php

namespace App\Livewire;

use App\Models\FamilyMember;
use App\Models\User;
use App\Support\Avatar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class AvatarBuilder extends Component
{
    public array $config = [];

    public ?FamilyMember $member = null;

    public function mount(?FamilyMember $member = null): void
    {
        $this->member = $member;
        $this->authorizeOrFail();
        $this->config = Avatar::normalize($this->target()->avatar_config);
    }

    private function target(): Model
    {
        if ($this->member) {
            return $this->member->user ?: $this->member;
        }

        return auth()->user();
    }

    private function authorizeOrFail(): void
    {
        if (! $this->member) {
            return;
        }

        $user = auth()->user();
        abort_unless($this->member->household_id === $user->household_id, 404);
        $isAdmin = $user->canManageHousehold($user->household);
        abort_unless($isAdmin || $this->member->user_id === $user->id, 403);
    }

    public function setSkin(string $color): void
    {
        $this->config['skin'] = $color;
    }

    public function setBodyType(string $type): void
    {
        $this->config['body_type'] = $type;
    }

    public function setHeight(string $height): void
    {
        $this->config['height'] = $height;
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

    public function setMouthColor(string $color): void
    {
        $this->config['mouth_color'] = $color;
    }

    public function setNose(string $style): void
    {
        $this->config['nose'] = $style;
    }

    public function setEars(string $style): void
    {
        $this->config['ears'] = $style;
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
        $this->config = Avatar::randomConfig();
    }

    public function save(): void
    {
        $this->authorizeOrFail();

        $target = $this->target();
        $target->update(['avatar_config' => Avatar::normalize($this->config)]);

        if ($target instanceof User) {
            $existing = $target->getRawOriginal('avatar');
            if ($existing && ! str_starts_with($existing, 'http')) {
                Storage::disk('public')->delete($existing);
                $target->update(['avatar' => null]);
            }
        }

        $this->dispatch('avatar-updated');
        session()->flash('status', 'Avatar saved.');
    }

    public function clearBuiltAvatar(): void
    {
        $this->authorizeOrFail();

        $this->target()->update(['avatar_config' => null]);
        $this->config = Avatar::defaultConfig();
        $this->dispatch('avatar-updated');
        session()->flash('status', 'Built avatar removed.');
    }

    public function render()
    {
        return view('livewire.avatar-builder', [
            'hasBuilt' => ! empty($this->target()->avatar_config),
            'opts' => [
                'skinTones' => Avatar::SKIN_TONES,
                'bodyTypes' => Avatar::BODY_TYPES,
                'heights' => Avatar::HEIGHTS,
                'hairColors' => Avatar::HAIR_COLORS,
                'topColors' => Avatar::TOP_COLORS,
                'bottomColors' => Avatar::BOTTOM_COLORS,
                'shoeColors' => Avatar::SHOE_COLORS,
                'hatColors' => Avatar::HAT_COLORS,
                'hairStyles' => Avatar::HAIR_STYLES,
                'eyeStyles' => Avatar::EYE_STYLES,
                'eyeColors' => Avatar::EYE_COLORS,
                'mouthStyles' => Avatar::MOUTH_STYLES,
                'mouthColors' => Avatar::MOUTH_COLORS,
                'noseStyles' => Avatar::NOSE_STYLES,
                'earStyles' => Avatar::EAR_STYLES,
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
