<?php
/**
 * Test form wrapper template
 */

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

$content      = $context['content']       ?? 'Test Form';
$renderSubmit = $context['render_submit'] ?? null;

$markup = '<form class="test-form-wrapper">' . $content;

if (is_callable($renderSubmit)) {
	$markup .= '<div class="test-form-submit-controls">' . $renderSubmit() . '</div>';
}

$markup .= '</form>';

return new ComponentRenderResult(markup: $markup);
