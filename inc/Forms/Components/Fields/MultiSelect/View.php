<?php
/**
 * Multi-select field component.
 * Clean component focused solely on form control markup.
 *
 * @var array{
 *     select_attributes:string,
 *     options_html:array<int,string>
 * } $context
 */

declare(strict_types=1);

$selectAttributes = isset($context['select_attributes']) ? trim((string) $context['select_attributes']) : '';
$optionsHtml      = isset($context['options_html']) && is_array($context['options_html']) ? $context['options_html'] : array();

ob_start();
?>
<select<?php echo $selectAttributes !== '' ? ' ' . $selectAttributes : ''; ?>>
	<?php foreach ($optionsHtml as $optionMarkup): ?>
		<?php echo $optionMarkup; ?>
	<?php endforeach; ?>
</select>
<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: (string) ob_get_clean(),
	context_schema: array(
	    'required' => array('select_attributes', 'options_html'),
	    'optional' => array(),
	    'defaults' => array(),
	)
);
