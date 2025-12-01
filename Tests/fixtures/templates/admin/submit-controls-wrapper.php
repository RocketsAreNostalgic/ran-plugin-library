<?php
/**
 * Test Submit Controls Wrapper Template
 */

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

$content = $context['inner_html'] ?? '';

return new ComponentRenderResult(
	markup: '<div class="submit-controls-wrapper">' . $content . '</div>',
	component_type: 'layout_wrapper'
);
