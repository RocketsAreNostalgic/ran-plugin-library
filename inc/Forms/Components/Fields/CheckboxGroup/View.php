<?php
/**
 * Checkbox group component template.
 * Clean component focused solely on form control markup.
 *
 * @var array{
 *     attributes:string,
 *     options_html:array<int,string>,
 *     legend:?string
 * } $context
 */

$attributes  = isset($context['attributes']) ? trim((string) $context['attributes']) : '';
$legend      = isset($context['legend']) ? (string) $context['legend'] : '';
$optionsHtml = isset($context['options_html']) && is_array($context['options_html']) ? $context['options_html'] : array();

ob_start();
?>
<fieldset class="checkbox-group"<?php echo $attributes !== '' ? ' ' . $attributes : ''; ?>>
	<?php if ($legend !== '') : ?>
		<legend><?php echo esc_html($legend); ?></legend>
	<?php endif; ?>
	<?php foreach ($optionsHtml as $optionMarkup) : ?>
		<?php echo $optionMarkup; ?>
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
	submits_data: true,
	component_type: 'input'
);
