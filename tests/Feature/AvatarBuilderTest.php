<?php

use App\Livewire\AvatarBuilder;
use App\Support\Avatar;
use Livewire\Livewire;

it('saves a built avatar config to the user', function () {
    $user = loginUser();

    Livewire::test(AvatarBuilder::class)
        ->call('setHairStyle', 'long')
        ->call('setTopStyle', 'hoodie')
        ->call('setTopColor', Avatar::TOP_COLORS[2])
        ->call('setHatStyle', 'beanie')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->hasBuiltAvatar())->toBeTrue();
    expect($user->avatar_config['hair']['style'])->toBe('long');
    expect($user->avatar_config['top']['style'])->toBe('hoodie');
    expect($user->avatar_config['hat']['style'])->toBe('beanie');
});

it('rejects invalid style values via normalization', function () {
    $user = loginUser();

    Livewire::test(AvatarBuilder::class)
        ->set('config.top.style', 'definitely-not-a-style')
        ->call('save');

    $user->refresh();
    // Normalization on save coerces unknown values back to default.
    expect($user->avatar_config['top']['style'])->toBe(Avatar::defaultConfig()['top']['style']);
});

it('clearing the built avatar restores the uploaded image fallback', function () {
    $user = loginUser();
    $user->update(['avatar_config' => Avatar::defaultConfig()]);

    expect($user->fresh()->hasBuiltAvatar())->toBeTrue();

    Livewire::test(AvatarBuilder::class)->call('clearBuiltAvatar');

    expect($user->fresh()->hasBuiltAvatar())->toBeFalse();
});

it('avatar accessor returns an svg data uri when a built avatar is present', function () {
    $user = loginUser();
    $user->update(['avatar_config' => Avatar::defaultConfig()]);

    $url = $user->fresh()->avatar;

    expect($url)->toStartWith('data:image/svg+xml;base64,');
});

it('randomize produces a fully valid config', function () {
    loginUser();

    $component = Livewire::test(AvatarBuilder::class)->call('randomize');

    $cfg = $component->get('config');
    expect($cfg['skin'])->toBeIn(Avatar::SKIN_TONES);
    expect($cfg['top']['style'])->toBeIn(Avatar::TOP_STYLES);
    expect($cfg['shoes']['style'])->toBeIn(Avatar::SHOE_STYLES);
    expect($cfg['height'])->toBeIn(Avatar::HEIGHTS);
});

it('saves the selected height', function () {
    $user = loginUser();

    Livewire::test(AvatarBuilder::class)
        ->call('setHeight', 'tall')
        ->call('save');

    expect($user->fresh()->avatar_config['height'])->toBe('tall');
});
