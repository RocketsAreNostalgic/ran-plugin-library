<?php
/**
 * Checkbox component template.
 * Clean component focused solely on form control markup.
 *
 * @var array{
 *     input_attributes:string,
 *     label_text:string,
 *     unchecked_value:?string,
 *     name:?string,
 *     id:?string
 * } $context
 */
$id              = isset($context['id']) ? (string) $context['id'] : '';
$inputAttributes = isset($context['input_attributes']) ? trim((string) $context['input_attributes']) : '';
$labelText       = isset($context['label_text']) ? (string) $context['label_text'] : '';
$uncheckedValue  = isset($context['unchecked_value']) ? (string) $context['unchecked_value'] : null;
$name            = isset($context['name']) ? (string) $context['name'] : null;

ob_start();
?>
<label for="<?php echo esc_attr($id); ?>">
	<input type="checkbox"<?php echo $inputAttributes !== '' ? ' ' . $inputAttributes : ''; ?>>
	<span><?php echo esc_html($labelText); ?></span>
</label>
<?php if ($uncheckedValue !== null && $name !== null) : ?>
	<input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($uncheckedValue); ?>">
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
	    'optional' => array('id', 'label_text', 'unchecked_value', 'name'),
	    'defaults' => array(
	        'label_text'      => '',
	        'unchecked_value' => 'off',
	        'name'            => null,
	    ),
	),
	component_type: 'input'
);
