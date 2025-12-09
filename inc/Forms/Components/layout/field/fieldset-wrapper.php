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
$inner_html  = $context['inner_html']  ?? '';
$style       = $context['style']       ?? 'bordered';
$required    = $context['required']    ?? false;

$before = (string) ($context['before'] ?? '');
$after  = (string) ($context['after'] ?? '');

$fieldset_classes = array(
	'kplr-fieldset',
	'kplr-fieldset--' . $style,
	$required ? 'kplr-fieldset--required' : '',
);
if ($style !== '') {
	$fieldset_classes[] = $style;
}

ob_start();
?>
<fieldset class="<?php echo esc_attr(implode(' ', array_filter($fieldset_classes))); ?>" data-kplr-group-id="<?php echo esc_attr($group_id); ?>">
    <?php if (!empty($title)): ?>
        <legend class="kplr-fieldset__legend<?php echo $required ? ' kplr-fieldset__legend--required' : ''; ?>">
            <?php echo esc_html($title); ?>
        </legend>
    <?php endif; ?>

    <?php if (!empty($description)): ?>
        <div class="kplr-fieldset__description">
            <?php echo esc_html($description); ?>
        </div>
    <?php endif; ?>

    <div class="kplr-fieldset__content">
        <?php if ($before !== ''): ?>
            <?php echo $before; // Hook output should already be escaped.?>
        <?php endif; ?>

        <?php echo $inner_html; // Already escaped?>

        <?php if ($after !== ''): ?>
            <?php echo $after; // Hook output should already be escaped.?>
        <?php endif; ?>
    </div>
</fieldset>
<?php
return new ComponentRenderResult(
	markup: (string) ob_get_clean(),
	component_type: ComponentType::LayoutWrapper
);
