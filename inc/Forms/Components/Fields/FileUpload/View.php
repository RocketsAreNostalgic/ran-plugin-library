<?php
/**
 * File upload field component.
 * Displays file input and shows previously uploaded files with preview/link.
 *
 * Expected $context keys:
 * - name: string Input name attribute (required).
 * - value: array|null The saved file data (url, filename, attachment_id, etc.).
 * - attributes: array<string,string|int|bool> Additional attributes for the <input> element.
 * - multiple: bool Whether to allow multiple selections.
 * - accept: string|array<string> Optional MIME types or file extensions.
 * - existing_files: array<int,array|string> Optional list of already uploaded files to display.
 */

declare(strict_types=1);

$name          = isset($context['name']) ? (string) $context['name'] : '';
$attributes    = isset($context['attributes']) && is_array($context['attributes']) ? $context['attributes'] : array();
$multiple      = !empty($context['multiple']);
$accept        = $context['accept'] ?? null;
$value         = $context['value']  ?? null;
$existingFiles = isset($context['existing_files']) && is_array($context['existing_files']) ? $context['existing_files'] : array();

// Build existing files list from saved value if not explicitly provided
if (empty($existingFiles) && !empty($value)) {
	if (is_array($value)) {
		// Check if it's a single file array or array of files
		if (isset($value['url'])) {
			$existingFiles = array($value);
		} elseif (isset($value[0]) && is_array($value[0])) {
			$existingFiles = $value;
		}
	}
}

if (!empty($existingFiles)) {
	unset($attributes['required'], $attributes['aria-required']);
}

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
if (!empty($context['required']) && empty($existingFiles)) {
	// Only require if no existing file
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

/**
 * Check if a file is an image based on MIME type.
 */
$isImage = static function (string $type): bool {
	return str_starts_with($type, 'image/');
};

ob_start();
?>
<div class="kplr-file-upload">
	<?php if (!empty($existingFiles)): ?>
		<div class="kplr-file-upload__existing">
			<p class="kplr-file-upload__label"><?php esc_html_e('Current file:', 'ran-plugin-lib'); ?></p>
			<ul class="kplr-file-upload__list">
				<?php foreach ($existingFiles as $file): ?>
					<?php
					// Handle both array format and legacy string format
					if (is_array($file)) {
						$fileUrl      = $file['url']           ?? '';
						$fileName     = $file['filename']      ?? basename($fileUrl);
						$fileType     = $file['type']          ?? '';
						$attachmentId = $file['attachment_id'] ?? 0;
					} else {
						$fileUrl      = '';
						$fileName     = (string) $file;
						$fileType     = '';
						$attachmentId = 0;
					}

					if (empty($fileName) && empty($fileUrl)) {
						continue;
					}
					?>
					<li class="kplr-file-upload__item">
						<?php if (!empty($fileUrl)): ?>
							<?php if ($isImage($fileType)): ?>
								<a href="<?php echo esc_url($fileUrl); ?>" target="_blank" class="kplr-file-upload__preview">
									<img src="<?php echo esc_url($fileUrl); ?>" alt="<?php echo esc_attr($fileName); ?>" class="kplr-file-upload__thumbnail" />
								</a>
							<?php endif; ?>
							<a href="<?php echo esc_url($fileUrl); ?>" target="_blank" class="kplr-file-upload__link">
								<?php echo esc_html($fileName); ?>
							</a>
						<?php else: ?>
							<span class="kplr-file-upload__filename"><?php echo esc_html($fileName); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="kplr-file-upload__hint"><?php esc_html_e('Upload a new file to replace the current one.', 'ran-plugin-lib'); ?></p>
		</div>
	<?php endif; ?>

	<input<?php echo $formatAttributes($attributes); ?> />
</div>
<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: (string) ob_get_clean(),
	context_schema: array(
		'required' => array('name'),
		'optional' => array('attributes', 'multiple', 'accept', 'existing_files', 'required', 'value'),
		'defaults' => array(
			'attributes'     => array(),
			'multiple'       => false,
			'accept'         => null,
			'existing_files' => array(),
			'required'       => false,
			'value'          => null,
		),
	)
);
