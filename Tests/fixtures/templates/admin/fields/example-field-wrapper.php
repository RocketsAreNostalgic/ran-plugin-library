<?php
/**
 * Example AdminSettings Field Wrapper Template
 *
 * This template demonstrates co-located field wrapper template structure for AdminSettings.
 * It shows how to create a modern field wrapper with enhanced styling and validation display.
 *
 * @var string $field_id The field identifier
 * @var string $label Field label
 * @var string $component_html Rendered component HTML
 * @var array $validation_warnings Validation warning messages
 * @var array $display_notices Display notice messages
 * @var string $description Field description/help text
 * @var bool $required Whether field is required
 * @var array $context Additional field context
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}


$field_id            = isset($context['field_id']) ? (string) $context['field_id'] : '';
$label               = isset($context['label']) ? (string) $context['label'] : '';
$component_html      = isset($context['component_html']) ? (string) $context['component_html'] : '';
$before              = isset($context['before']) ? (string) $context['before'] : '';
$after               = isset($context['after']) ? (string) $context['after'] : '';
$validation_warnings = isset($context['validation_warnings']) && is_array($context['validation_warnings']) ? $context['validation_warnings'] : array();
$display_notices     = isset($context['display_notices'])     && is_array($context['display_notices']) ? $context['display_notices'] : array();
$description         = isset($context['description']) ? (string) $context['description'] : '';
$required            = isset($context['required']) ? (bool) $context['required'] : false;

$has_warnings    = !empty($validation_warnings);
$has_notices     = !empty($display_notices);
$wrapper_classes = array('example-field-wrapper');

if ($has_warnings) {
	$wrapper_classes[] = 'has-warnings';
}

if ($required) {
	$wrapper_classes[] = 'is-required';
}
?>

<div class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>" data-field-id="<?php echo esc_attr($field_id); ?>">
    <div class="example-field-label-container">
        <label for="<?php echo esc_attr($field_id); ?>" class="example-field-label">
            <?php echo esc_html($label); ?>
            <?php if ($required): ?>
                <span class="example-required-indicator" aria-label="<?php esc_attr_e('Required field', 'textdomain'); ?>">*</span>
            <?php endif; ?>
        </label>

        <?php if (!empty($description)): ?>
            <p class="example-field-description">
                <?php echo esc_html($description); ?>
            </p>
        <?php endif; ?>
    </div>

    <div class="example-field-input-container">
        <?php echo $component_html; ?>

        <?php if ($has_warnings): ?>
            <div class="example-field-warnings" role="alert">
                <?php foreach ($validation_warnings as $warning): ?>
                    <div class="example-warning-message">
                        <span class="example-warning-icon" aria-hidden="true">⚠</span>
                        <span class="example-warning-text"><?php echo esc_html($warning); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($has_notices): ?>
            <div class="example-field-notices">
                <?php foreach ($display_notices as $notice): ?>
                    <div class="example-notice-message">
                        <span class="example-notice-icon" aria-hidden="true">ℹ</span>
                        <span class="example-notice-text"><?php echo esc_html($notice); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.example-field-wrapper {
    display: grid;
    grid-template-columns: 200px 1fr;
    gap: 20px;
    padding: 15px 0;
    border-bottom: 1px solid #f0f0f1;
    align-items: start;
}

.example-field-wrapper:last-child {
    border-bottom: none;
}

.example-field-label-container {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.example-field-label {
    font-weight: 600;
    color: #1d2327;
    margin: 0;
    line-height: 1.4;
}

.example-required-indicator {
    color: #d63638;
    font-weight: bold;
    margin-left: 3px;
}

.example-field-description {
    margin: 0;
    font-size: 13px;
    color: #646970;
    line-height: 1.4;
}

.example-field-input-container {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.example-field-warnings {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.example-warning-message {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    background: #fcf2f2;
    border: 1px solid #f0a0a0;
    border-radius: 4px;
    font-size: 13px;
}

.example-warning-icon {
    color: #d63638;
    font-size: 14px;
    flex-shrink: 0;
}

.example-warning-text {
    color: #8a2424;
    line-height: 1.4;
}

.example-field-notices {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.example-notice-message {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    background: #f0f6fc;
    border: 1px solid #a0c4e0;
    border-radius: 4px;
    font-size: 13px;
}

.example-notice-icon {
    color: #0073aa;
    font-size: 14px;
    flex-shrink: 0;
}

.example-notice-text {
    color: #0a4b78;
    line-height: 1.4;
}

.example-field-wrapper.has-warnings .example-field-label {
    color: #8a2424;
}

.example-field-wrapper.is-required .example-field-label {
    position: relative;
}

/* Responsive design */
@media (max-width: 768px) {
    .example-field-wrapper {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .example-field-label-container {
        order: 1;
    }

    .example-field-input-container {
        order: 2;
    }
}

/* Focus styles for accessibility */
.example-field-wrapper input:focus,
.example-field-wrapper select:focus,
.example-field-wrapper textarea:focus {
    outline: 2px solid #0073aa;
    outline-offset: 2px;
}
</style>
