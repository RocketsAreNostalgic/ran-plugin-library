<?php
/**
 * Custom field wrapper template
 */

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

$markup = '<div class="custom-field-wrapper">' . ($context['content'] ?? 'Custom Field') . '</div>';

return new ComponentRenderResult(markup: $markup);
