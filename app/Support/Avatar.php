<?php

namespace App\Support;

class Avatar
{
    public const SKIN_TONES = ['#f8d8c4', '#f0c8a3', '#d6a37a', '#a87151', '#7a4a2b', '#4a2c1a'];

    public const HAIR_COLORS = ['#1c1410', '#3a2418', '#6b3f1f', '#a86f3d', '#d9a441', '#e8e2d0', '#b8b8b8', '#cf3a2f', '#5b2a86'];

    public const TOP_COLORS = ['#2563eb', '#dc2626', '#16a34a', '#f59e0b', '#7c3aed', '#0f172a', '#ffffff', '#ec4899', '#0ea5e9'];

    public const BOTTOM_COLORS = ['#1e293b', '#3b82f6', '#0f172a', '#6b4423', '#374151', '#7f1d1d', '#365314', '#a16207'];

    public const SHOE_COLORS = ['#111827', '#ffffff', '#dc2626', '#1e40af', '#92400e', '#f5f5f5', '#16a34a'];

    public const HAT_COLORS = ['#111827', '#dc2626', '#1e40af', '#16a34a', '#f59e0b', '#ffffff', '#7c3aed'];

    public const EYE_COLORS = ['#1c1410', '#3a2418', '#3b6b3a', '#1d4e89', '#7a4a2b', '#5b2a86', '#9b9b9b'];

    public const FACIAL_HAIR_COLORS = ['#1c1410', '#3a2418', '#6b3f1f', '#a86f3d', '#d9a441', '#e8e2d0', '#b8b8b8', '#cf3a2f'];

    public const HAIR_STYLES = ['short', 'long', 'buzz', 'bun', 'bald'];

    public const EYE_STYLES = ['default', 'happy', 'wink'];

    public const MOUTH_STYLES = ['smile', 'neutral', 'grin'];

    public const FACIAL_HAIR_STYLES = ['none', 'mustache', 'beard'];

    public const TOP_STYLES = ['tshirt', 'dress-shirt', 'hoodie', 'dress'];

    public const BOTTOM_STYLES = ['pants', 'shorts', 'skirt'];

    public const SHOE_STYLES = ['sneakers', 'boots', 'flats'];

    public const HAT_STYLES = ['none', 'cap', 'beanie', 'tophat'];

    public static function defaultConfig(): array
    {
        return [
            'skin' => '#f0c8a3',
            'hair' => ['style' => 'short', 'color' => '#3a2418'],
            'eyes' => 'default',
            'eye_color' => '#1c1410',
            'mouth' => 'smile',
            'facial_hair' => 'none',
            'facial_hair_color' => '#3a2418',
            'top' => ['style' => 'tshirt', 'color' => '#2563eb'],
            'bottom' => ['style' => 'pants', 'color' => '#1e293b'],
            'shoes' => ['style' => 'sneakers', 'color' => '#111827'],
            'hat' => ['style' => 'none', 'color' => '#111827'],
        ];
    }

    /**
     * Merge a partial config with defaults so missing keys are filled in.
     */
    public static function normalize(?array $config): array
    {
        $defaults = self::defaultConfig();
        if (! $config) {
            return $defaults;
        }

        return [
            'skin' => self::pick($config['skin'] ?? null, self::SKIN_TONES, $defaults['skin']),
            'hair' => [
                'style' => self::pickEnum($config['hair']['style'] ?? null, self::HAIR_STYLES, $defaults['hair']['style']),
                'color' => self::pick($config['hair']['color'] ?? null, self::HAIR_COLORS, $defaults['hair']['color']),
            ],
            'eyes' => self::pickEnum($config['eyes'] ?? null, self::EYE_STYLES, $defaults['eyes']),
            'eye_color' => self::pick($config['eye_color'] ?? null, self::EYE_COLORS, $defaults['eye_color']),
            'mouth' => self::pickEnum($config['mouth'] ?? null, self::MOUTH_STYLES, $defaults['mouth']),
            'facial_hair' => self::pickEnum($config['facial_hair'] ?? null, self::FACIAL_HAIR_STYLES, $defaults['facial_hair']),
            'facial_hair_color' => self::pick($config['facial_hair_color'] ?? null, self::FACIAL_HAIR_COLORS, $defaults['facial_hair_color']),
            'top' => [
                'style' => self::pickEnum($config['top']['style'] ?? null, self::TOP_STYLES, $defaults['top']['style']),
                'color' => self::pick($config['top']['color'] ?? null, self::TOP_COLORS, $defaults['top']['color']),
            ],
            'bottom' => [
                'style' => self::pickEnum($config['bottom']['style'] ?? null, self::BOTTOM_STYLES, $defaults['bottom']['style']),
                'color' => self::pick($config['bottom']['color'] ?? null, self::BOTTOM_COLORS, $defaults['bottom']['color']),
            ],
            'shoes' => [
                'style' => self::pickEnum($config['shoes']['style'] ?? null, self::SHOE_STYLES, $defaults['shoes']['style']),
                'color' => self::pick($config['shoes']['color'] ?? null, self::SHOE_COLORS, $defaults['shoes']['color']),
            ],
            'hat' => [
                'style' => self::pickEnum($config['hat']['style'] ?? null, self::HAT_STYLES, $defaults['hat']['style']),
                'color' => self::pick($config['hat']['color'] ?? null, self::HAT_COLORS, $defaults['hat']['color']),
            ],
        ];
    }

    private static function pick(?string $value, array $palette, string $fallback): string
    {
        return in_array($value, $palette, true) ? $value : $fallback;
    }

    private static function pickEnum(?string $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }
}
