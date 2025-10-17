<?php
/**
 * Modern AdminSettings Field Wrapper Template
 *
 * A modern field wrapper with clean styling, proper accessibility,
 * and comprehensive validation message handling.
 *
 * Expected $context keys:
 * - field_id: string - Field identifier
 * - label: string - Field label
 * - component_html: string - Rendered component HTML
 * - validation_warnings: array - Validation warning messages
 * - display_notices: array - Display notice messages
 * - description: string - Field description/help text (optional)
 * - required: bool - Whether field is required (default: false)
 * - field_type: string - Type of field for styling (optional)
 * - layout: string - 'vertical', 'horizontal' (default: vertical)
 *
 * @package RanPluginLib\Forms\Views\Admin\Fields
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

// Extract context variables
$field_id            = $context['field_id']            ?? '';
$label               = $context['label']               ?? '';
$component_html      = $context['component_html']      ?? '';
$validation_warnings = $context['validation_warnings'] ?? array();
$display_notices     = $context['display_notices']     ?? array();
$description         = $context['description']         ?? '';
$required            = $context['required']            ?? false;
$field_type          = $context['field_type']          ?? '';
$layout              = $context['layout']              ?? 'vertical';

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

    <style>
        .field-wrapper {
            margin-bottom: 1.5rem;
            transition: all 0.2s ease;
        }

        .field-wrapper--vertical {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .field-wrapper--horizontal {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .field-wrapper--horizontal .field-wrapper__label-section {
            flex: 0 0 200px;
            padding-top: 0.5rem;
        }

        .field-wrapper--horizontal .field-wrapper__input-section {
            flex: 1;
            min-width: 0;
        }

        .field-wrapper__label {
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin: 0;
            line-height: 1.4;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .field-wrapper__required-indicator {
            color: #ef4444;
            font-weight: 500;
        }

        .field-wrapper__input-container {
            position: relative;
        }

        .field-wrapper__description {
            font-size: 0.75rem;
            color: #6b7280;
            margin: 0.25rem 0 0 0;
            line-height: 1.4;
        }

        .field-wrapper__messages {
            margin-top: 0.5rem;
        }

        .field-wrapper__warnings {
            margin-bottom: 0.5rem;
        }

        .field-wrapper__warning {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 0.375rem;
            padding: 0.5rem 0.75rem;
            font-size: 0.75rem;
            color: #92400e;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .field-wrapper__warning:last-child {
            margin-bottom: 0;
        }

        .field-wrapper__warning::before {
            content: '⚠';
            flex-shrink: 0;
        }

        .field-wrapper__notices {
            margin-bottom: 0.5rem;
        }

        .field-wrapper__notice {
            background: #dbeafe;
            border: 1px solid #3b82f6;
            border-radius: 0.375rem;
            padding: 0.5rem 0.75rem;
            font-size: 0.75rem;
            color: #1e40af;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .field-wrapper__notice:last-child {
            margin-bottom: 0;
        }

        .field-wrapper__notice::before {
            content: 'ℹ';
            flex-shrink: 0;
        }

        /* Field type specific styling */
        .field-wrapper--checkbox,
        .field-wrapper--radio {
            flex-direction: row;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .field-wrapper--checkbox .field-wrapper__input-container,
        .field-wrapper--radio .field-wrapper__input-container {
            order: -1;
            flex-shrink: 0;
            padding-top: 0.125rem;
        }

        .field-wrapper--checkbox .field-wrapper__label,
        .field-wrapper--radio .field-wrapper__label {
            cursor: pointer;
            user-select: none;
        }

        /* Focus and interaction states */
        .field-wrapper:focus-within {
            outline: 2px solid #3b82f6;
            outline-offset: 2px;
            border-radius: 0.375rem;
        }

        .field-wrapper--has-warnings {
            background: #fffbeb;
            border: 1px solid #f59e0b;
            border-radius: 0.5rem;
            padding: 1rem;
        }

        .field-wrapper--has-warnings .field-wrapper__label {
            color: #92400e;
        }

        /* Component styling enhancements */
        .field-wrapper input[type="text"],
        .field-wrapper input[type="email"],
        .field-wrapper input[type="password"],
        .field-wrapper input[type="number"],
        .field-wrapper input[type="url"],
        .field-wrapper input[type="tel"],
        .field-wrapper select,
        .field-wrapper textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            line-height: 1.4;
            transition: all 0.2s ease;
            background: #fff;
        }

        .field-wrapper input:focus,
        .field-wrapper select:focus,
        .field-wrapper textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 1px #3b82f6;
        }

        .field-wrapper input:disabled,
        .field-wrapper select:disabled,
        .field-wrapper textarea:disabled {
            background: #f9fafb;
            color: #6b7280;
            cursor: not-allowed;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .field-wrapper--horizontal {
                flex-direction: column;
                gap: 0.5rem;
            }

            .field-wrapper--horizontal .field-wrapper__label-section {
                flex: none;
                padding-top: 0;
            }

            .field-wrapper--has-warnings {
                padding: 0.75rem;
            }
        }

        /* Accessibility enhancements */
        .field-wrapper[aria-invalid="true"] input,
        .field-wrapper[aria-invalid="true"] select,
        .field-wrapper[aria-invalid="true"] textarea {
            border-color: #ef4444;
        }

        .field-wrapper[aria-invalid="true"] .field-wrapper__label {
            color: #dc2626;
        }

        /* Animation for messages */
        .field-wrapper__warning,
        .field-wrapper__notice {
            animation: slideInDown 0.3s ease-out;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>

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
            <?php echo $component_html; // Already escaped?>
        </div>

        <?php if (!empty($description) && $layout === 'vertical'): ?>
            <div class="field-wrapper__description">
                <?php echo esc_html($description); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($validation_warnings) || !empty($display_notices)): ?>
            <div class="field-wrapper__messages">
                <?php if (!empty($validation_warnings)): ?>
                    <div class="field-wrapper__warnings" role="alert">
                        <?php foreach ($validation_warnings as $warning): ?>
                            <div class="field-wrapper__warning">
                                <?php echo esc_html($warning); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($display_notices)): ?>
                    <div class="field-wrapper__notices">
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
return (string) ob_get_clean();
