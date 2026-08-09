<?php

declare(strict_types=1);

namespace Timadey\LazySettings\Contracts;

/**
 * Marker interface for the optional UI add-on.
 *
 * When a settings enum ALSO implements this (via the UI package's trait),
 * the core engine and the settings:sync command switch to "labelled" mode:
 * labels()/description()/group()/options() are used for human-facing output.
 * Core inspects interface_exists()/instanceof at runtime — no dependency and
 * no config toggle required.
 */
interface SettingLabels
{
    /** Human-readable label, e.g. "MTN Direct Plan Provider". */
    public function label(): string;

    /** Optional help/description shown in admin panels. */
    public function description(): ?string;

    /** Group/section the setting belongs to for admin organisation. */
    public function group(): string;

    /** Options for rendering select/checkbox inputs (may be keyed). */
    public function options(): array;
}