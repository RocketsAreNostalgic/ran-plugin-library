<?php
/**
 * Configurable button component.
 *
 * Expected $context keys:
 * - label: string Button text (required).
 * - attributes: array<string,string|int|bool> Optional HTML attributes.
 * - button_attributes: string Preformatted attribute string for the <button> element.
 * - type: string Optional button type attribute (defaults to "button").
 * - disabled: bool Optional disabled flag.
 * - icon_html: string Optional HTML rendered before the label.
 * - variant: string Button style variant ("primary" by default, supports "secondary").
 */

declare(strict_types=1);

$label            = isset($context['label']) ? (string) $context['label'] : '';
$buttonAttributes = isset($context['button_attributes']) ? trim((string) $context['button_attributes']) : '';
$icon_html        = isset($context['icon_html']) ? (string) $context['icon_html'] : '';

ob_start();
?>
<button<?php echo $buttonAttributes !== '' ? ' ' . $buttonAttributes : ''; ?>>
	<?php if ($icon_html !== ''): ?>
		<span class="kplr-button__icon"><?php echo $icon_html; ?></span>
	<?php endif; ?>
	<span class="kplr-button__label"><?php echo esc_html($label); ?></span>
</button>
<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: (string) ob_get_clean(),
	script: null,
	style: null,
	requires_media: false,
	repeatable: false,
	context_schema: array(
	    'required' => array('label'),
	    'optional' => array('attributes', 'button_attributes', 'type', 'disabled', 'icon_html', 'variant'),
	    'defaults' => array(
	        'attributes'        => array(),
	        'button_attributes' => '',
	        'type'              => 'button',
	        'disabled'          => false,
	        'icon_html'         => '',
	        'variant'           => 'primary',
	    ),
	)
);
