<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

$content      = $context['inner_html']    ?? '';
$renderSubmit = $context['render_submit'] ?? null;

if (is_callable($renderSubmit)) {
	$content .= $renderSubmit();
}

return new ComponentRenderResult(
	markup: '<div class="theme-page">' . $content . '</div>',
	component_type: 'layout_wrapper'
);
