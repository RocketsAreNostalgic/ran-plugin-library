<?php
/**
 * File upload field component.
 *
 * Expected $context keys:
 * - name: string Input name attribute (required).
 * - attributes: array<string,string|int|bool> Additional attributes for the <input> element.
 * - multiple: bool Whether to allow multiple selections (developer should add [] to name when true).
 * - accept: string|array<string> Optional MIME types or file extensions.
 * - description: string Optional help text beneath the control.
 * - description_id: string Optional ID tying description to the control.
 * - existing_files: array<int,string> Optional list of already uploaded filenames/labels to display.
 */

declare(strict_types=1);

$name          = isset($context['name']) ? (string) $context['name'] : '';
$attributes    = isset($context['attributes']) && is_array($context['attributes']) ? $context['attributes'] : array();
$multiple      = !empty($context['multiple']);
$accept        = $context['accept'] ?? null;
$description   = isset($context['description']) ? (string) $context['description'] : '';
$descriptionId = isset($context['description_id']) ? (string) $context['description_id'] : '';
$existingFiles = isset($context['existing_files']) && is_array($context['existing_files']) ? $context['existing_files'] : array();
$warnings      = isset($context['warnings'])       && is_array($context['warnings']) ? $context['warnings'] : array();
$notices       = isset($context['notices'])        && is_array($context['notices']) ? $context['notices'] : array();

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
if ($description !== '' && $descriptionId === '') {
	$descriptionId = $name . '__desc';
}
if ($descriptionId !== '') {
	$attributes['aria-describedby'] = trim(($attributes['aria-describedby'] ?? '') . ' ' . $descriptionId);
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
<?php if ($description !== ''): ?>
	<p class="ran-forms__description" id="<?php echo esc_attr($descriptionId); ?>">
		<?php echo esc_html($description); ?>
	</p>
<?php endif; ?>
<?php if (!empty($existingFiles)): ?>
	<ul class="ran-forms__file-existing">
		<?php foreach ($existingFiles as $fileLabel): ?>
			<li><?php echo esc_html((string) $fileLabel); ?></li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
<?php
return array(
	'markup'         => (string) ob_get_clean(),
	'script'         => null,
	'style'          => null,
	'requires_media' => false,
	'repeatable'     => true, // consider multiple uploads instead
	'context_schema' => array(
	    'required' => array('name'),
	    'optional' => array('attributes', 'multiple', 'accept', 'description', 'description_id', 'existing_files', 'required', 'warnings', 'notices'),
	    'defaults' => array(
	        'attributes'     => array(),
	        'multiple'       => false,
	        'accept'         => null,
	        'description'    => '',
	        'description_id' => '',
	        'existing_files' => array(),
	        'required'       => false,
	        'warnings'       => array(),
	        'notices'        => array(),
	    ),
	),
);
