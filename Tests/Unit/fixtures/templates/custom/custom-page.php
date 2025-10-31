<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

$title        = $context['title']         ?? 'Default Title';
$renderSubmit = $context['render_submit'] ?? null;

$content = '<div class="custom-page">' . $title;
if (is_callable($renderSubmit)) {
	$content .= '<div class="custom-submit-controls">' . $renderSubmit() . '</div>';
}
$content .= '</div>';

return new ComponentRenderResult(
	markup: $content,
	component_type: 'layout_wrapper'
);
