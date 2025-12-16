<?php
/**
 * Checkbox component template.
 * Clean component focused solely on form control markup.
 *
 * @var array{
 *     input_attributes:string,
 *     label:string,
 *     unchecked_value:?string,
 *     name:?string,
 *     id:?string
 * } $context
 */
$id              = isset($context['id']) ? (string) $context['id'] : '';
$inputAttributes = isset($context['input_attributes']) ? trim((string) $context['input_attributes']) : '';
$checkboxAttrs   = isset($context['checkbox_attributes']) ? trim((string) $context['checkbox_attributes']) : $inputAttributes;
$hiddenAttrs     = isset($context['hidden_attributes']) ? trim((string) $context['hidden_attributes']) : '';
$labelText       = isset($context['label']) ? (string) $context['label'] : '';
$uncheckedValue  = isset($context['unchecked_value']) ? (string) $context['unchecked_value'] : null;
$name            = isset($context['name']) ? (string) $context['name'] : null;

ob_start();
// Hidden input MUST come before checkbox so checkbox value overwrites it when checked
if ($hiddenAttrs !== '') : ?>
<input<?php echo ' ' . $hiddenAttrs; ?>>
<?php elseif ($uncheckedValue !== null && $name !== null) : ?>
<input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($uncheckedValue); ?>">
<?php endif; ?>
<label for="<?php echo esc_attr($id); ?>">
	<input type="checkbox"<?php echo $checkboxAttrs !== '' ? ' ' . $checkboxAttrs : ''; ?>>
	<span><?php echo esc_html($labelText); ?></span>
</label>
<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: (string) ob_get_clean(),
	context_schema: array(
	    'required' => array('input_attributes'),
	    'optional' => array('id', 'label', 'unchecked_value', 'name'),
	    'defaults' => array(
	        'label'           => '',
	        'unchecked_value' => 'off',
	        'name'            => null,
	    ),
	)
);
