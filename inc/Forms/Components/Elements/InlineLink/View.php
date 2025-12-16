<?php
/**
 * Inline link component for contextual actions.
 *
 * Expected $context keys:
 * - label: string Link text (required).
 * - url: string HREF destination (required).
 * - attributes: array<string,string|int|bool> Optional HTML attributes.
 * - target: string Optional target attribute.
 * - rel: string Optional rel attribute; defaults to "noopener noreferrer" when target is _blank.
 * - icon_html: string Optional HTML rendered before the label.
 * - link_attributes: string Optional preformatted attribute string.
 */

declare(strict_types=1);

$label          = isset($context['label']) ? (string) $context['label'] : '';
$url            = isset($context['url']) ? (string) $context['url'] : '';
$attributes     = isset($context['attributes']) && is_array($context['attributes']) ? $context['attributes'] : array();
$target         = isset($context['target']) ? (string) $context['target'] : '';
$rel            = isset($context['rel']) ? (string) $context['rel'] : '';
$icon_html      = isset($context['icon_html']) ? (string) $context['icon_html'] : '';
$linkAttributes = isset($context['link_attributes']) ? trim((string) $context['link_attributes']) : '';

ob_start();

if ($linkAttributes === '') {
	$normalizedAttributes = $attributes;
	$baseClasses          = array('kplr-link');
	if (isset($normalizedAttributes['class'])) {
		$baseClasses[] = (string) $normalizedAttributes['class'];
	}
	$normalizedAttributes['class'] = trim(implode(' ', array_filter(array_map('trim', $baseClasses))));
	$normalizedAttributes['href']  = $url;
	if ($target !== '') {
		$normalizedAttributes['target'] = $target;
		if ($rel === '' && strtolower($target) === '_blank') {
			$rel = 'noopener noreferrer';
		}
	}
	if ($rel !== '') {
		$normalizedAttributes['rel'] = $rel;
	}

	$linkAttributeParts = array();
	foreach ($normalizedAttributes as $key => $value) {
		if ($value === null || $value === '' || $value === false) {
			continue;
		}
		$linkAttributeParts[] = sprintf('%s="%s"', esc_attr((string) $key), esc_attr((string) $value));
	}
	$linkAttributes = implode(' ', $linkAttributeParts);
}

$linkAttributes = $linkAttributes !== '' ? ' ' . trim($linkAttributes) : '';

?>
<a<?php echo $linkAttributes; ?>>
	<?php if ($icon_html !== ''): ?>
		<span class="kplr-link__icon"><?php echo $icon_html; ?></span>
	<?php endif; ?>
	<span class="kplr-link__label"><?php echo esc_html($label); ?></span>
</a>
<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: (string) ob_get_clean(),
	context_schema: array(
	    'required' => array('label', 'url'),
	    'optional' => array('attributes', 'link_attributes', 'target', 'rel', 'icon_html'),
	    'defaults' => array(
	        'attributes'      => array(),
	        'link_attributes' => '',
	        'target'          => '',
	        'rel'             => '',
	        'icon_html'       => '',
	    ),
	)
);
