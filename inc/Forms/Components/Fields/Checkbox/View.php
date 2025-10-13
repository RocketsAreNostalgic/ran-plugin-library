<?php
/**
 * Checkbox component template.
 *
 * @var array{
 *     input_attributes:string,
 *     label_text:string,
 *     description:string,
 *     unchecked_value:?string,
 *     name:?string,
 *     id:?string,
 *     warnings:array<int,string>,
 *     notices:array<int,string>
 * } $context
 */
$id              = isset($context['id']) ? (string) $context['id'] : '';
$inputAttributes = isset($context['input_attributes']) ? trim((string) $context['input_attributes']) : '';
$labelText       = isset($context['label_text']) ? (string) $context['label_text'] : '';
$description     = isset($context['description']) ? (string) $context['description'] : '';
$uncheckedValue  = isset($context['unchecked_value']) ? (string) $context['unchecked_value'] : null;
$name            = isset($context['name']) ? (string) $context['name'] : null;
$descriptionId   = isset($context['description_id']) ? (string) $context['description_id'] :  $id . '_desc';
$warnings        = isset($context['warnings']) && is_array($context['warnings']) ? $context['warnings'] : array();
$notices         = isset($context['notices'])  && is_array($context['notices']) ? $context['notices'] : array();

ob_start();
?>
<label for="<?php echo esc_attr($id); ?>">
	<input type="checkbox"<?php echo $inputAttributes !== '' ? ' ' . $inputAttributes : ''; ?>>
	<span><?php echo esc_html($labelText); ?></span>
</label>
<?php if (!empty($warnings)) : ?>
	<?php foreach ($warnings as $warning) : ?>
		<p class="form-message form-message--warning"><?php echo esc_html($warning); ?></p>
	<?php endforeach; ?>
<?php endif; ?>
<?php if (!empty($notices)) : ?>
	<?php foreach ($notices as $notice) : ?>
		<p class="form-message form-message--notice"><?php echo esc_html($notice); ?></p>
	<?php endforeach; ?>
<?php endif; ?>
<?php if ($description !== '') : ?>
	<p class="description"<?php echo $descriptionId !== '' ? ' id="' . esc_attr($descriptionId) . '"' : ''; ?>><?php echo esc_html($description); ?></p>
<?php endif; ?>
<?php if ($uncheckedValue !== null && $name !== null) : ?>
	<input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($uncheckedValue); ?>">
<?php endif; ?>
<?php
$markup = (string) ob_get_clean();

return array(
	'markup'         => $markup,
	'script'         => null,
	'style'          => null,
	'requires_media' => false,
	'repeatable'     => false,
	'context_schema' => array(
	    'required' => array('input_attributes'),
	    'optional' => array('id', 'description', 'description_id', 'label_text', 'unchecked_value', 'name', 'warnings', 'notices'),
	    'defaults' => array(
	        'description'     => '',
	        'description_id'  => '',
	        'label_text'      => '',
	        'unchecked_value' => 'off',
	        'name'            => null,
	        'warnings'        => array(),
	        'notices'         => array(),
	    ),
	),
);
