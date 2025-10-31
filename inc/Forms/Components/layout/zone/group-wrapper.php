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
 * - content: string - Inner HTML content.
 * - layout: string - 'vertical', 'horizontal', 'grid' (default: vertical)
 * - columns: int - Number of columns for grid layout (default: 2)
 * - spacing: string - 'compact', 'normal', 'spacious' (default: normal)
 *
 * @package RanPluginLib\Forms\Views\Shared
 */

use Ran\PluginLib\Forms\Component\ComponentType;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;

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
return new ComponentRenderResult(
	markup: (string) ob_get_clean(),
	component_type: ComponentType::LayoutWrapper
);
