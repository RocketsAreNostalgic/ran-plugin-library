<?php
/**
 * Checkbox option template for checkbox groups.
 *
 * @var array{
 *     input_attributes:string,
 *     label:string,
 *     description:string,
 *     description_id:?string
 * } $context
	*/

$inputAttributes = isset($context['input_attributes']) ? trim((string) $context['input_attributes']) : '';
$label           = isset($context['label']) ? (string) $context['label'] : '';
$description     = isset($context['description']) ? (string) $context['description'] : '';
$descriptionId   = isset($context['description_id']) ? (string) $context['description_id'] : '';

ob_start();
?>
<label>
	<input type="checkbox"<?php echo $inputAttributes !== '' ? ' ' . $inputAttributes : ''; ?>>
	<span><?php echo esc_html($label); ?></span>
</label>
<?php if ($description !== '') : ?>
	<p class="description"<?php echo $descriptionId !== '' ? ' id="' . esc_attr($descriptionId) . '"' : ''; ?>><?php echo esc_html($description); ?></p>
<?php endif; ?>
<?php
return array(
	'markup'         => (string) ob_get_clean(),
	'script'         => null,
	'style'          => null,
	'requires_media' => false,
	'repeatable'     => false,
	'context_schema' => array(
	    'required' => array('input_attributes'),
	    'optional' => array('label', 'description', 'description_id'),
	    'defaults' => array(
	        'label'          => '',
	        'description'    => '',
	        'description_id' => '',
	    ),
	),
);
