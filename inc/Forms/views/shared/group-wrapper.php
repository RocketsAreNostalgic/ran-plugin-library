<?php
/**
 * Modern AdminSettings Group Template
 *
 * A modern group container for organizing related fields with clean styling.
 * Works seamlessly with modern section and page layouts.
 *
 * Expected $context keys:
 * - group_id: string - Group identifier
 * - title: string - Group title (optional)
 * - description: string - Group description (optional)
 * - content: string - Group content (fields)
 * - layout: string - 'vertical', 'horizontal', 'grid' (default: vertical)
 * - columns: int - Number of columns for grid layout (default: 2)
 * - spacing: string - 'compact', 'normal', 'spacious' (default: normal)
 *
 * @package RanPluginLib\Forms\Views\Admin\Groups
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
$layout      = $context['layout']      ?? 'vertical';
$columns     = $context['columns']     ?? 2;
$spacing     = $context['spacing']     ?? 'normal';

$group_classes = array(
    'group-wrapper',
    "group-wrapper--{$layout}",
    "group-wrapper--{$spacing}"
);

ob_start();
?>
<div class="<?php echo esc_attr(implode(' ', $group_classes)); ?>" data-group-id="<?php echo esc_attr($group_id); ?>">

    <style>
        .group-wrapper {
            background: #f8fafc;
            border: 1px solid #e1e5e9;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.2s ease;
        }

        .group-wrapper:hover {
            border-color: #cbd5e1;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .group-wrapper__header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e1e5e9;
            background: #fff;
            border-radius: 0.5rem 0.5rem 0 0;
        }

        .group-wrapper__title {
            font-size: 1rem;
            font-weight: 600;
            color: #374151;
            margin: 0 0 0.25rem 0;
            line-height: 1.3;
        }

        .group-wrapper__description {
            font-size: 0.875rem;
            color: #6b7280;
            margin: 0;
            line-height: 1.4;
        }

        .group-wrapper__content {
            padding: 1.5rem;
        }

        /* Layout variations */
        .group-wrapper--vertical .group-wrapper__content {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .group-wrapper--horizontal .group-wrapper__content {
            display: flex;
            flex-direction: row;
            gap: 1.5rem;
            align-items: flex-start;
        }

        .group-wrapper--horizontal .group-wrapper__content > * {
            flex: 1;
            min-width: 0;
        }

        .group-wrapper--grid .group-wrapper__content {
            display: grid;
            grid-template-columns: repeat(<?php echo (int) $columns; ?>, 1fr);
            gap: 1.5rem;
            align-items: start;
        }

        /* Spacing variations */
        .group-wrapper--compact .group-wrapper__content {
            padding: 1rem;
            gap: 0.75rem;
        }

        .group-wrapper--compact .group-wrapper__header {
            padding: 0.75rem 1rem;
        }

        .group-wrapper--spacious .group-wrapper__content {
            padding: 2rem;
            gap: 2rem;
        }

        .group-wrapper--spacious .group-wrapper__header {
            padding: 1.5rem 2rem;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .group-wrapper--horizontal .group-wrapper__content,
            .group-wrapper--grid .group-wrapper__content {
                display: flex;
                flex-direction: column;
                gap: 1rem;
            }

            .group-wrapper__header {
                padding: 1rem;
            }

            .group-wrapper__content {
                padding: 1rem;
            }

            .group-wrapper--spacious .group-wrapper__content {
                padding: 1.5rem 1rem;
            }
        }

        /* Field styling within groups */
        .group-wrapper .form-field-wrapper {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            padding: 1rem;
            transition: border-color 0.2s ease;
        }

        .group-wrapper .form-field-wrapper:focus-within {
            border-color: #3b82f6;
            box-shadow: 0 0 0 1px #3b82f6;
        }

        .group-wrapper .form-field-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        /* Accessibility */
        .group-wrapper:focus-within {
            outline: 2px solid #3b82f6;
            outline-offset: 2px;
        }
    </style>

    <?php if (!empty($title) || !empty($description)): ?>
        <div class="group-wrapper__header">
            <?php if (!empty($title)): ?>
                <h4 class="group-wrapper__title"><?php echo esc_html($title); ?></h4>
            <?php endif; ?>

            <?php if (!empty($description)): ?>
                <p class="group-wrapper__description"><?php echo esc_html($description); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="group-wrapper__content">
        <?php echo $content; // Already escaped?>
    </div>
</div>
<?php
return (string) ob_get_clean();
