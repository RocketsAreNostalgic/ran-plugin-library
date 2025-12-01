<?php
/**
 * Field Wrapper Template
 *
 * A field wrapper with clean styling, proper accessibility,
 * and comprehensive validation message handling.
 *
 * Expected $context keys:
 * - field_id: string - Field identifier
 * - label: string - Field label
 * - inner_html: string - Rendered inner HTML content
 * - validation_warnings: array - Validation warning messages
 * - display_notices: array - Display notice messages
 * - description: string - Field description/help text (optional)
 * - required: bool - Whether field is required (default: false)
 * - field_type: string - Type of field for styling (optional)
 * - layout: string - 'vertical', 'horizontal' (default: vertical)
 *
 * @package RanPluginLib\Forms\Views\Admin\Fields
 */

use Ran\PluginLib\Forms\Component\ComponentType;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

$inner_html = isset($context['inner_html']) ? (string) $context['inner_html'] : '';

// Early return if no inner_html
if ($inner_html === '') {
	return new ComponentRenderResult(
		markup: '',
		component_type: ComponentType::LayoutWrapper
	);
}

$before = (string) ($context['before'] ?? '');
$after  = (string) ($context['after'] ?? '');

$description         = $context['description']         ?? '';
$validation_warnings = $context['validation_warnings'] ?? array();
$display_notices     = $context['display_notices']     ?? array();

$required   = $context['required']   ?? false;
$field_type = $context['field_type'] ?? '';
$layout     = $context['layout']     ?? 'vertical';

$field_id = $context['field_id'] ?? '';
$label    = $context['label']    ?? '';

$wrapper_classes = array(
    'field-wrapper',
    "field-wrapper--{$layout}",
    !empty($field_type) ? "field-wrapper--{$field_type}" : '',
    $required ? 'field-wrapper--required' : '',
    !empty($validation_warnings) ? 'field-wrapper--has-warnings' : '',
    !empty($display_notices) ? 'field-wrapper--has-notices' : ''
);
ob_start();

?>
<div class="<?php echo esc_attr(implode(' ', array_filter($wrapper_classes))); ?>" data-field-id="<?php echo esc_attr($field_id); ?>">
    <div class="field-wrapper__label-section">
        <?php if (!empty($label)): ?>
            <label for="<?php echo esc_attr($field_id); ?>" class="field-wrapper__label">
                <?php echo esc_html($label); ?>
                <?php if ($required): ?>
                    <span class="field-wrapper__required-indicator" aria-label="required">*</span>
                <?php endif; ?>
            </label>
        <?php endif; ?>

        <?php if (!empty($description) && $layout === 'horizontal'): ?>
            <div class="field-wrapper__description">
                <?php echo esc_html($description); ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="field-wrapper__input-section">
        <div class="field-wrapper__input-container">
            <?php if ($before !== ''): ?>
                <?php echo $before; // Hook output should already be escaped.?>
            <?php endif; ?>
            <?php echo $inner_html // Already escaped?>
            <?php if ($after !== ''): ?>
                <?php echo $after; // Hook output should already be escaped.?>
            <?php endif; ?>
        </div>
		<?php if ($description !== '') : ?>
			<p class="description"><?php echo esc_html($description); ?></p>
		<?php endif; ?>
        <?php if (!empty($description) && $layout === 'vertical'): ?>
            <div class="field-wrapper__description">
                <?php echo esc_html($description); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($validation_warnings) || !empty($display_notices)): ?>
            <div class="field-wrapper__messages">
                <?php if (!empty($validation_warnings)): ?>
                    <div class="form-field-warnings" role="alert">
                        <?php foreach ($validation_warnings as $warning): ?>
                            <div class="form-field-warning error">
                                <?php echo esc_html($warning); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($display_notices)): ?>
                    <div class="form-field-notices">
                        <?php foreach ($display_notices as $notice): ?>
                            <div class="field-wrapper__notice">
                                <?php echo esc_html($notice); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
return new ComponentRenderResult(
	markup: (string) ob_get_clean(),
	component_type: ComponentType::LayoutWrapper
);
