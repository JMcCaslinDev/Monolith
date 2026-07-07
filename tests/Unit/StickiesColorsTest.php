<?php

declare(strict_types=1);

namespace Tests\Unit;

use Stickies\StickyColors;
use Stickies\StickyService;
use Tests\Support\TestCase;

/** Sticky color palette validation used by note save and board rendering. */
final class StickiesColorsTest extends TestCase
{
    /** All palette keys are valid selectable colors. */
    public function test_all_constants_are_valid(): void
    {
        foreach (StickyColors::ALL as $color) {
            $this->assertTrue(StickyColors::isValid($color));
        }
    }

    /** Unknown colors are rejected so arbitrary CSS cannot be injected. */
    public function test_unknown_color_is_invalid(): void
    {
        $this->assertFalse(StickyColors::isValid('red'));
        $this->assertFalse(StickyColors::isValid('#fff'));
    }

    /** Palette returns a hex for every allowed color. */
    public function test_palette_covers_all_colors(): void
    {
        $palette = StickyColors::palette();
        foreach (StickyColors::ALL as $color) {
            $this->assertArrayHasKey($color, $palette);
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $palette[$color]);
        }
    }
}
