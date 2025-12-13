<?php
/**
 * Checkbox option template for checkbox groups.
 * Clean component focused solely on form control markup.
 *
 * @var array{
 *     input_attributes:string,
 *     label:string,
 *     description:string,
 *     description_id:string
 * } $context
 */

$inputAttributes = isset($context['input_attributes']) ? trim((string) $context['input_attributes']) : '';
$label           = isset($context['label']) ? (string) $context['label'] : '';
$description     = isset($context['description']) ? (string) $context['description'] : '';
$description_id  = isset($context['description_id']) ? (string) $context['description_id'] : '';

ob_start();
?>
<label>
	<input type="checkbox"<?php echo $inputAttributes !== '' ? ' ' . $inputAttributes : ''; ?>>
	<span><?php echo esc_html($label); ?></span>
</label>
<?php if ($description !== '' && $description_id !== ''): ?>
	<span id="<?php echo esc_attr($description_id); ?>" class="description"><?php echo esc_html($description); ?></span>
<?php endif; ?>
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
	    'optional' => array('label', 'description', 'description_id'),
	    'defaults' => array(
	        'label'          => '',
	        'description'    => '',
	        'description_id' => '',
	    ),
	)
);
