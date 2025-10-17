<?php
/**
 * Fieldset Group Template
 *
 * A semantic fieldset-based group with proper accessibility and legend.
 * Perfect for groups that represent a logical collection of related fields.
 *
 * Expected $context keys:
 * - group_id: string - Group identifier
 * - title: string - Group title (becomes legend)
 * - description: string - Group description (optional)
 * - content: string - Group content (fields)
 * - style: string - 'bordered', 'minimal', 'highlighted' (default: bordered)
 * - required: bool - Whether any field in group is required (default: false)
 *
 * @package RanPluginLib\Forms\Views\Shared
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

// Extract context variables
$group_id    = $context['group_id']    ?? '';
$title       = $context['title']       ?? '';
$description = $context['description'] ?? '';
$content     = $context['content']     ?? '';
$style       = $context['style']       ?? 'bordered';
$required    = $context['required']    ?? false;

$fieldset_classes = array(
    'fieldset-group',
    "fieldset-group--{$style}",
    $required ? 'fieldset-group--required' : ''
);

ob_start();
?>
<fieldset class="<?php echo esc_attr(implode(' ', array_filter($fieldset_classes))); ?>" data-group-id="<?php echo esc_attr($group_id); ?>">

    <style>
        .fieldset-group {
            margin-bottom: 1.5rem;
            padding: 0;
            border: none;
            background: #fff;
        }

        /* Style variations */
        .fieldset-group--bordered {
            border: 2px solid #e1e5e9;
            border-radius: 0.5rem;
            padding: 1.5rem;
        }

        .fieldset-group--minimal {
            border-bottom: 1px solid #e1e5e9;
            padding-bottom: 1.5rem;
        }

        .fieldset-group--highlighted {
            background: #f0f9ff;
            border: 2px solid #0ea5e9;
            border-radius: 0.5rem;
            padding: 1.5rem;
        }

        .fieldset-group__legend {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1e293b;
            padding: 0 0.5rem;
            margin-bottom: 1rem;
            line-height: 1.3;
        }

        .fieldset-group--bordered .fieldset-group__legend {
            background: #fff;
            padding: 0 0.75rem;
            margin-left: -0.75rem;
            position: relative;
            top: -0.5rem;
            margin-bottom: 0.5rem;
        }

        .fieldset-group--minimal .fieldset-group__legend {
            border-bottom: 2px solid #3b82f6;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
            display: inline-block;
        }

        .fieldset-group--highlighted .fieldset-group__legend {
            color: #0c4a6e;
            background: #f0f9ff;
        }

        .fieldset-group__legend--required::after {
            content: ' *';
            color: #ef4444;
            font-weight: normal;
        }

        .fieldset-group__description {
            font-size: 0.875rem;
            color: #64748b;
            margin: 0 0 1.5rem 0;
            line-height: 1.5;
        }

        .fieldset-group--highlighted .fieldset-group__description {
            color: #0c4a6e;
        }

        .fieldset-group__content {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        /* Field styling within fieldsets */
        .fieldset-group .form-field-wrapper {
            background: transparent;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            padding: 1rem;
            transition: all 0.2s ease;
        }

        .fieldset-group--highlighted .form-field-wrapper {
            background: rgba(255, 255, 255, 0.7);
            border-color: #bae6fd;
        }

        .fieldset-group .form-field-wrapper:focus-within {
            border-color: #3b82f6;
            box-shadow: 0 0 0 1px #3b82f6;
            background: #fff;
        }

        .fieldset-group .form-field-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .fieldset-group--highlighted .form-field-label {
            color: #1e293b;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .fieldset-group--bordered,
            .fieldset-group--highlighted {
                padding: 1rem;
            }

            .fieldset-group__legend {
                font-size: 1rem;
            }

            .fieldset-group--bordered .fieldset-group__legend {
                margin-left: -0.5rem;
                padding: 0 0.5rem;
            }

            .fieldset-group .form-field-wrapper {
                padding: 0.75rem;
            }
        }

        /* Accessibility enhancements */
        .fieldset-group:focus-within {
            outline: 2px solid #3b82f6;
            outline-offset: 2px;
        }

        .fieldset-group[aria-invalid="true"] {
            border-color: #ef4444;
        }

        .fieldset-group[aria-invalid="true"] .fieldset-group__legend {
            color: #dc2626;
        }

        /* Animation for content */
        .fieldset-group__content > * {
            animation: slideInUp 0.3s ease-out;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>

    <?php if (!empty($title)): ?>
        <legend class="fieldset-group__legend <?php echo $required ? 'fieldset-group__legend--required' : ''; ?>">
            <?php echo esc_html($title); ?>
        </legend>
    <?php endif; ?>

    <?php if (!empty($description)): ?>
        <div class="fieldset-group__description">
            <?php echo esc_html($description); ?>
        </div>
    <?php endif; ?>

    <div class="fieldset-group__content">
        <?php echo $content; // Already escaped?>
    </div>
</fieldset>
<?php
return (string) ob_get_clean();
