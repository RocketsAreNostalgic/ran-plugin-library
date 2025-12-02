<?php
/**
 * Shared Section Template
 *
 * Basic section container template for grouping related fields.
 * Expects pre-rendered content from the caller.
 *
 * Context:
 * - content: string Pre-rendered section content (fields, groups, etc.)
 * - section_id: string Optional section identifier
 * - title: string Optional section title
 * - description: string Optional section description
 * - before: string Optional content before section
 * - after: string Optional content after section
 *
 * @package RanPluginLib\Forms\Views\Shared
 */

use Ran\PluginLib\Forms\Component\ComponentType;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

$section_id  = $context['section_id']  ?? '';
$title       = $context['title']       ?? '';
$description = $context['description'] ?? '';
$inner_html  = $context['inner_html']  ?? '';
$before      = (string) ($context['before'] ?? '');
$after       = (string) ($context['after'] ?? '');
$style       = trim((string) ($context['style'] ?? ''));

$section_classes = array('kplr-section');
if ($style !== '') {
	$section_classes[] = $style;
}

ob_start();
?>
<div class="<?php echo esc_attr(implode(' ', $section_classes)); ?>" data-kplr-section-id="<?php echo esc_attr($section_id); ?>">
	<?php if (!empty($title)): ?>
		<h3 class="kplr-section__title"><?php echo esc_html($title); ?></h3>
	<?php endif; ?>

	<?php if (!empty($description)): ?>
		<p class="kplr-section__description"><?php echo esc_html($description); ?></p>
	<?php endif; ?>

	<?php if ($before !== ''): ?>
		<?php echo $before; // Hook output should already be escaped.?>
	<?php endif; ?>

	<div class="kplr-section__content">
		<?php echo $inner_html; // Pre-rendered inner HTML from caller.?>
	</div>

	<?php if ($after !== ''): ?>
		<?php echo $after; // Hook output should already be escaped.?>
	<?php endif; ?>
</div>
<?php
return new ComponentRenderResult(
	markup: (string) ob_get_clean(),
	component_type: ComponentType::LayoutWrapper
);
