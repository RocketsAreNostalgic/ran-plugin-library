<?php
/**
 * Radio group field component.
 * Clean component focused solely on form control markup.
 *
 * @var array{
 *     legend:?string,
 *     attributes:string,
 *     options_html:array<int,string>
 * } $context
 */

$legend       = isset($context['legend']) ? (string) $context['legend'] : '';
$attributes   = isset($context['attributes']) ? (string) $context['attributes'] : '';
$options_html = isset($context['options_html']) && is_array($context['options_html']) ? $context['options_html'] : array();

$fieldset_attr = trim($attributes);
$output_attr   = $fieldset_attr !== '' ? ' ' . $fieldset_attr : '';

ob_start();
?>
<fieldset<?php echo $output_attr; ?>>
	<?php if ($legend !== ''): ?>
		<legend><?php echo esc_html($legend); ?></legend>
	<?php endif; ?>

	<?php foreach ($options_html as $option_markup): ?>
		<?php echo $option_markup; ?>
	<?php endforeach; ?>
</fieldset>
<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: (string) ob_get_clean(),
	script: null,
	style: null,
	requires_media: false,
	repeatable: false,
	context_schema: array(
	    'required' => array('attributes', 'options_html'),
	    'optional' => array('legend'),
	    'defaults' => array(
	        'legend' => '',
	    ),
	),
	component_type: 'input'
);
