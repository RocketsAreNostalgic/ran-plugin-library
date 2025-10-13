<?php
/**
 * Single radio option component.
 *
 * @var array{
 *     input_attributes:string,
 *     label:string,
 *     description:string,
 *     description_id:?string,
 *     label_attributes:string
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
<?php if ($description !== ''): ?>
	<p class="description"<?php echo $description_id !== '' ? ' id="' . esc_attr($description_id) . '"' : ''; ?>><?php echo esc_html($description); ?></p>
<?php endif; ?>
<?php
return array(
	'markup'         => (string) ob_get_clean(),
	'script'         => null,
	'style'          => null,
	'requires_media' => false,
	'repeatable'     => false,
	'context_schema' => array(
	    'required' => array('input_attributes', 'label'),
	    'optional' => array('description', 'description_id', 'label_attributes'),
	    'defaults' => array(
	        'description'      => '',
	        'description_id'   => '',
	        'label_attributes' => '',
	    ),
	),
);
