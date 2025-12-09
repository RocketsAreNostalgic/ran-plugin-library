<?php
/**
 * Test field wrapper template
 */

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

$markup = '<div class="test-field-wrapper">' . ($context['inner_html'] ?? 'Test Field') . '</div>';

return new ComponentRenderResult(markup: $markup);
