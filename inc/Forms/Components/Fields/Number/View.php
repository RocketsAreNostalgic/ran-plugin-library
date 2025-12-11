<?php
/**
 * Number component template.
 *
 * Renders a numeric input element. Uses the same markup as Input component
 * since both render an <input> element with type-specific attributes.
 *
 * @var array{
 *     input_attributes: string,
 *     input_type: string,
 *     min?: int|float,
 *     max?: int|float,
 *     step?: int|float,
 *     repeatable?: bool
 * } $context
 */

$inputAttributes = isset($context['input_attributes']) ? trim((string) $context['input_attributes']) : '';

ob_start();
?>
<input<?php echo $inputAttributes !== '' ? ' ' . $inputAttributes : ''; ?>>
<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: (string) ob_get_clean(),
	script: null,
	style: null,
	requires_media: false,
	repeatable: true,
	context_schema: array(
		'required' => array('input_attributes', 'input_type'),
		'optional' => array('min', 'max', 'step', 'repeatable'),
		'defaults' => array(),
	)
);
