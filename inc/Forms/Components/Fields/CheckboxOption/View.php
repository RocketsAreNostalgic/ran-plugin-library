<?php
/**
 * Checkbox option template for checkbox groups.
 * Clean component focused solely on form control markup.
 *
 * @var array{
 *     input_attributes:string,
 *     label:string
 * } $context
	*/

$inputAttributes = isset($context['input_attributes']) ? trim((string) $context['input_attributes']) : '';
$label           = isset($context['label']) ? (string) $context['label'] : '';

ob_start();
?>
<label>
	<input type="checkbox"<?php echo $inputAttributes !== '' ? ' ' . $inputAttributes : ''; ?>>
	<span><?php echo esc_html($label); ?></span>
</label>
<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: (string) ob_get_clean(),
	script: null,
	style: null,
	requires_media: false,
	repeatable: false,
	context_schema: array(
	    'required' => array('input_attributes'),
	    'optional' => array('label'),
	    'defaults' => array(
	        'label' => '',
	    ),
	),
	component_type: 'input'
);
