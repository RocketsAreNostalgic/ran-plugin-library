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
 * - style: string - Optional style class
 * - before: string - Optional content before the group
 * - after: string - Optional content after the group
 *
 * @package RanPluginLib\Forms\Views\Shared
 */

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

// Extract context variables
$group_id    = $context['group_id']    ?? '';
$title       = $context['title']       ?? '';
$description = $context['description'] ?? '';
$inner_html  = $context['inner_html']  ?? '';
$style       = trim((string) ($context['style'] ?? ''));
$before      = (string) ($context['before'] ?? '');
$after       = (string) ($context['after'] ?? '');

$group_classes = array(
	'kplr-group',
);
if ($style !== '') {
	$group_classes[] = $style;
}

ob_start();
?>
<div class="<?php echo esc_attr(implode(' ', $group_classes)); ?>" data-kplr-group-id="<?php echo esc_attr($group_id); ?>">

    <?php if (!empty($title) || !empty($description)): ?>
        <div class="kplr-group__header">
			<?php if (!empty($title)) : ?>
				<h4 class="kplr-group__title"><?php echo esc_html($title); ?></h4>
			<?php endif; ?>
			<?php if (!empty($description)) : ?>
				<p class="kplr-group__description"><?php echo esc_html($description); ?></p>
			<?php endif; ?>
		</div>
    <?php endif; ?>

    <div class="kplr-group__content">
        <?php if ($before !== ''): ?>
            <?php echo $before; // Hook output should already be escaped.?>
        <?php endif; ?>

        <?php echo $inner_html; // Already escaped?>

        <?php if ($after !== ''): ?>
            <?php echo $after; // Hook output should already be escaped.?>
        <?php endif; ?>
    </div>
</div>
<?php
return new ComponentRenderResult(
	markup: (string) ob_get_clean()
);
