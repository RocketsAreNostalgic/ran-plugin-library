<?php
/**
 * Media picker field utilizing the WordPress media modal.
 * Clean component focused solely on form control markup.
 */

declare(strict_types=1);

$inputAttributes = isset($context['input_attributes']) ? (string) $context['input_attributes'] : '';
$selectLabel     = isset($context['select_label']) ? (string) $context['select_label'] : 'Select media';
$replaceLabel    = isset($context['replace_label']) ? (string) $context['replace_label'] : 'Replace media';
$removeLabel     = isset($context['remove_label']) ? (string) $context['remove_label'] : 'Remove';
$buttonId        = isset($context['button_id']) ? (string) $context['button_id'] : '';
$removeId        = isset($context['remove_id']) ? (string) $context['remove_id'] : '';
$hasSelection    = !empty($context['has_selection']);
$previewHtml     = isset($context['preview_html']) ? (string) $context['preview_html'] : '';
$multiple        = !empty($context['multiple']);
$removeDisabled  = $hasSelection ? '' : 'disabled="disabled" aria-disabled="true"';

ob_start();
?>
<div class="kplr-media-picker" data-kplr-media-picker>
	<input <?php echo $inputAttributes; ?> />
	<div class="kplr-media-picker__actions">
		<button
			id="<?php echo esc_attr($buttonId); ?>"
			type="button"
			class="button kplr-media-picker__button"
			data-default-label="<?php echo esc_attr($selectLabel); ?>"
			data-replace-label="<?php echo esc_attr($replaceLabel); ?>"
			data-multiple="<?php echo $multiple ? 'true' : 'false'; ?>"
		>
			<?php echo esc_html($hasSelection ? $replaceLabel : $selectLabel); ?>
		</button>
		<button
			id="<?php echo esc_attr($removeId); ?>"
			type="button"
			class="button button-link-delete kplr-media-picker__remove"
			<?php echo $removeDisabled; ?>
		>
			<?php echo esc_html($removeLabel); ?>
		</button>
	</div>
	<div class="kplr-media-picker__preview" data-kplr-media-preview>
		<?php echo $previewHtml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>
	</div>
</div>
<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: (string) ob_get_clean(),
	script: null,
	style: null,
	requires_media: true,
	repeatable: false,
	context_schema: array(
	    'required' => array('input_attributes', 'button_id', 'remove_id'),
	    'optional' => array('select_label', 'replace_label', 'remove_label', 'has_selection', 'preview_html', 'multiple'),
	    'defaults' => array(
	        'select_label'  => 'Select media',
	        'replace_label' => 'Replace media',
	        'remove_label'  => 'Remove',
	        'has_selection' => false,
	        'preview_html'  => '',
	        'multiple'      => false,
	    ),
	),
	component_type: 'input'
);
