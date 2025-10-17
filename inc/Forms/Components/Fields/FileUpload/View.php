<?php
/**
 * File upload field component.
 * Clean component focused solely on form control markup.
 *
 * Expected $context keys:
 * - name: string Input name attribute (required).
 * - attributes: array<string,string|int|bool> Additional attributes for the <input> element.
 * - multiple: bool Whether to allow multiple selections (developer should add [] to name when true).
 * - accept: string|array<string> Optional MIME types or file extensions.
 * - existing_files: array<int,string> Optional list of already uploaded filenames/labels to display.
 */

declare(strict_types=1);

$name          = isset($context['name']) ? (string) $context['name'] : '';
$attributes    = isset($context['attributes']) && is_array($context['attributes']) ? $context['attributes'] : array();
$multiple      = !empty($context['multiple']);
$accept        = $context['accept'] ?? null;
$existingFiles = isset($context['existing_files']) && is_array($context['existing_files']) ? $context['existing_files'] : array();


if (!isset($name)) {
	if (isset($context['id'])) {
		$name = $context['id'];
	} else {
		throw new \InvalidArgumentException('File field requires a name attribute.');
	}
}

$attributes['name'] = $name;
$attributes['type'] = 'file';
if ($multiple) {
	$attributes['multiple'] = 'multiple';
}
if (!empty($context['required'])) {
	$attributes['required']      = 'required';
	$attributes['aria-required'] = 'true';
}
if ($accept !== null) {
	if (is_array($accept)) {
		$attributes['accept'] = implode(',', array_map('strval', $accept));
	} else {
		$attributes['accept'] = (string) $accept;
	}
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

ob_start();
?>
<input<?php echo $formatAttributes($attributes); ?> />
<?php if (!empty($existingFiles)): ?>
	<ul class="ran-forms__file-existing">
		<?php foreach ($existingFiles as $fileLabel): ?>
			<li><?php echo esc_html((string) $fileLabel); ?></li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: (string) ob_get_clean(),
	script: null,
	style: null,
	requires_media: false,
	repeatable: true, // consider multiple uploads instead
	context_schema: array(
	    'required' => array('name'),
	    'optional' => array('attributes', 'multiple', 'accept', 'existing_files', 'required'),
	    'defaults' => array(
	        'attributes'     => array(),
	        'multiple'       => false,
	        'accept'         => null,
	        'existing_files' => array(),
	        'required'       => false,
	    ),
	),
	submits_data: true,
	component_type: 'form_field'
);
