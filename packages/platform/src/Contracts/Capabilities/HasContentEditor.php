<?php

namespace Nreljed\Platform\Contracts\Capabilities;

/**
 * The feature extends the shared content editor with per-field configuration.
 *
 * Both maps are merged across enabled features and handed to the editor, so a
 * feature contributes only its own field definitions.
 */
interface HasContentEditor
{
    /**
     * Meta-field formatting rules (e.g. how a date or currency meta value is
     * rendered in the editor preview).
     *
     * @return array<string, mixed>
     */
    public function getMetaFormattingConfig(): array;

    /**
     * Repeater field definitions available to this feature's content type.
     *
     * @return array<string, mixed>
     */
    public function getRepeaterConfig(): array;
}
