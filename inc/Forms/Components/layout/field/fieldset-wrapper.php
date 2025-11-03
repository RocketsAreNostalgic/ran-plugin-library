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
$content     = $context['content']     ?? '';
$style       = $context['style']       ?? 'bordered';
$required    = $context['required']    ?? false;
$before      = isset($context['before']) ? (string) $context['before'] : '';
$after       = isset($context['after'])  ? (string) $context['after']  : '';

$fieldset_classes = array(
    'fieldset-group',
    "fieldset-group--{$style}",
    $required ? 'fieldset-group--required' : ''
);

ob_start();
?>
<fieldset class="<?php echo esc_attr(implode(' ', array_filter($fieldset_classes))); ?>" data-group-id="<?php echo esc_attr($group_id); ?>">
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
        <?php if ($before !== ''): ?>
            <?php echo $before; // Hook output should already be escaped.?>
        <?php endif; ?>

        <?php echo $content; // Already escaped?>

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
