<?php
/**
 * Button link component rendered as an <a> element.
 *
 * Expected $context keys:
 * - label: string Link text (required).
 * - url: string HREF destination (required).
 * - attributes: array<string,string|int|bool> Optional HTML attributes.
 * - target: string Optional target attribute (e.g. "_blank").
 * - rel: string Optional rel attribute; defaults to "noopener noreferrer" when target is _blank.
 * - icon_html: string Optional HTML rendered before the label.
 */

declare(strict_types=1);

$label          = isset($context['label']) ? (string) $context['label'] : '';
$url            = isset($context['url']) ? (string) $context['url'] : '';
$attributes     = isset($context['attributes']) && is_array($context['attributes']) ? $context['attributes'] : array();
$target         = isset($context['target']) ? (string) $context['target'] : '';
$rel            = isset($context['rel']) ? (string) $context['rel'] : '';
$icon_html      = isset($context['icon_html']) ? (string) $context['icon_html'] : '';
$linkAttributes = isset($context['link_attributes']) ? trim((string) $context['link_attributes']) : '';

if ($linkAttributes === '') {
	$normalizedAttributes = $attributes;
	$baseClasses          = array('kplr-button', 'kplr-button--link');
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

	$formatAttributes = static function (array $attrs): string {
		$parts = array();
		foreach ($attrs as $key => $value) {
			if ($value === null || $value === '' || $value === false) {
				continue;
			}
			$parts[] = sprintf('%s="%s"', esc_attr((string) $key), esc_attr((string) $value));
		}

		return $parts ? ' ' . implode(' ', $parts) : '';
	};

	$linkAttributes = $formatAttributes($normalizedAttributes);
}

ob_start();
?>
<a<?php echo $linkAttributes !== '' ? ' ' . $linkAttributes : ''; ?>>
	<?php if ($icon_html !== ''): ?>
		<span class="kplr-button__icon"><?php echo $icon_html; ?></span>
	<?php endif; ?>
	<span class="kplr-button__label"><?php echo esc_html($label); ?></span>
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
