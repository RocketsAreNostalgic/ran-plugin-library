<?php
/**
 * Single radio option component.
 * Clean component focused solely on form control markup.
 *
 * @var array{
 *     input_attributes:string,
 *     label:string,
 *     label_attributes:string
 * } $context
*/

$input_attributes = isset($context['input_attributes']) ? (string) $context['input_attributes'] : '';
$label_attributes = isset($context['label_attributes']) ? (string) $context['label_attributes'] : '';
$label            = isset($context['label']) ? (string) $context['label'] : '';

$label_attr = trim((string) $label_attributes);
$input_attr = trim((string) $input_attributes);
$label_attr = $label_attr !== '' ? ' ' . $label_attr  : '';
$input_attr = $input_attr !== '' ? ' ' . $input_attr  : '';

ob_start();
?>
<label<?php echo $label_attr; ?>>
	<input type="radio"<?php echo $input_attr; ?>>
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
	    'required' => array('input_attributes', 'label'),
	    'optional' => array('label_attributes'),
	    'defaults' => array(
	        'label_attributes' => '',
	    ),
	),
	submits_data: true,
	component_type: 'input'
);
