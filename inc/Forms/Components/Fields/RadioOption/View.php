<?php
/**
 * Single radio option component.
 * Clean component focused solely on form control markup.
 *
 * @var array{
 *     input_attributes:string,
 *     label:string,
 *     label_attributes:string,
 *     description:string,
 *     description_id:string
 * } $context
*/

$input_attributes = isset($context['input_attributes']) ? (string) $context['input_attributes'] : '';
$label_attributes = isset($context['label_attributes']) ? (string) $context['label_attributes'] : '';
$label            = isset($context['label']) ? (string) $context['label'] : '';
$description      = isset($context['description']) ? (string) $context['description'] : '';
$description_id   = isset($context['description_id']) ? (string) $context['description_id'] : '';

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
<?php if ($description !== '' && $description_id !== ''): ?>
	<span id="<?php echo esc_attr($description_id); ?>" class="description"><?php echo esc_html($description); ?></span>
<?php endif; ?>
<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: (string) ob_get_clean(),
	context_schema: array(
	    'required' => array('input_attributes', 'label'),
	    'optional' => array('label_attributes', 'description', 'description_id'),
	    'defaults' => array(
	        'label_attributes' => '',
	        'description'      => '',
	        'description_id'   => '',
	    ),
	)
);
