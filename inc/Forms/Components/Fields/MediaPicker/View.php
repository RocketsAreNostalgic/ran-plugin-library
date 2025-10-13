<?php
/**
 * Media picker field utilizing the WordPress media modal.
 */

declare(strict_types=1);

$inputAttributes = isset($context['input_attributes']) ? (string) $context['input_attributes'] : '';
$selectLabel     = isset($context['select_label']) ? (string) $context['select_label'] : 'Select media';
$replaceLabel    = isset($context['replace_label']) ? (string) $context['replace_label'] : 'Replace media';
$removeLabel     = isset($context['remove_label']) ? (string) $context['remove_label'] : 'Remove';
$description     = isset($context['description']) ? (string) $context['description'] : '';
$descriptionId   = isset($context['description_id']) ? (string) $context['description_id'] : '';
$buttonId        = isset($context['button_id']) ? (string) $context['button_id'] : '';
$removeId        = isset($context['remove_id']) ? (string) $context['remove_id'] : '';
$hasSelection    = !empty($context['has_selection']);
$previewHtml     = isset($context['preview_html']) ? (string) $context['preview_html'] : '';
$multiple        = !empty($context['multiple']);
$removeDisabled  = $hasSelection ? '' : 'disabled="disabled" aria-disabled="true"';
$warnings        = isset($context['warnings']) && is_array($context['warnings']) ? $context['warnings'] : array();
$notices         = isset($context['notices'])  && is_array($context['notices']) ? $context['notices'] : array();

ob_start();
?>
<div class="ran-forms__media-picker" data-ran-forms-media-picker>
	<input <?php echo $inputAttributes; ?> />
	<div class="ran-forms__media-picker-actions">
		<button
			id="<?php echo esc_attr($buttonId); ?>"
			type="button"
			class="button ran-forms__media-picker-button"
			data-default-label="<?php echo esc_attr($selectLabel); ?>"
			data-replace-label="<?php echo esc_attr($replaceLabel); ?>"
			data-multiple="<?php echo $multiple ? 'true' : 'false'; ?>"
		>
			<?php echo esc_html($hasSelection ? $replaceLabel : $selectLabel); ?>
		</button>
		<button
			id="<?php echo esc_attr($removeId); ?>"
			type="button"
			class="button button-link-delete ran-forms__media-picker-remove"
			<?php echo $removeDisabled; ?>
		>
			<?php echo esc_html($removeLabel); ?>
		</button>
	</div>
	<div class="ran-forms__media-picker-preview" data-ran-forms-media-preview>
		<?php echo $previewHtml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>
	</div>
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
</div>
<?php
return array(
	'markup'         => (string) ob_get_clean(),
	'script'         => null,
	'style'          => null,
	'requires_media' => true,
	'repeatable'     => false,
	'context_schema' => array(
	    'required' => array('input_attributes', 'button_id', 'remove_id'),
	    'optional' => array('select_label', 'replace_label', 'remove_label', 'description', 'description_id', 'has_selection', 'preview_html', 'multiple', 'warnings', 'notices'),
	    'defaults' => array(
	        'select_label'   => 'Select media',
	        'replace_label'  => 'Replace media',
	        'remove_label'   => 'Remove',
	        'description'    => '',
	        'description_id' => '',
	        'has_selection'  => false,
	        'preview_html'   => '',
	        'multiple'       => false,
	        'warnings'       => array(),
	        'notices'        => array(),
	    ),
	),
);
