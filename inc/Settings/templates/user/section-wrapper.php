<?php
/**
 * Template for rendering a section in user profile context.
 * This template handles table-based constraints for WordPress profile pages.
 *
 * @var array{
 *     section_id?: string,
 *     title?: string,
 *     description?: string,
 *     content: string,
 *     before?: string,
 *     after?: string,
 *     fields?: array
 * } $context
 */

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

$section_id  = isset($context['section_id']) ? (string) $context['section_id'] : '';
$title       = isset($context['title']) ? (string) $context['title'] : '';
$description = isset($context['description']) ? (string) $context['description'] : '';
$inner_html  = isset($context['inner_html']) ? (string) $context['inner_html'] : '';
$style       = trim((string) ($context['style'] ?? ''));

$before = (string) ($context['before'] ?? '');
$after  = (string) ($context['after'] ?? '');

$section_classes = array('kplr-section');
if ($style !== '') {
	$section_classes[] = $style;
}

$after_classes = array('kplr-section__after');
if ($style !== '') {
	$after_classes[] = $style;
}

ob_start();
?>
<tr class="<?php echo esc_attr(implode(' ', $section_classes)); ?>" data-kplr-section-id="<?php echo esc_attr($section_id); ?>">
	<th class="kplr-section__header" colspan="2">
		<?php if ($title !== '') : ?>
			<h3 class="kplr-section__title"><?php echo esc_html($title); ?></h3>
		<?php endif; ?>
		<?php if ($description !== '') : ?>
			<p class="kplr-section__description"><?php echo wp_kses_post($description); ?></p>
		<?php endif; ?>
		<?php echo $before; ?>
	</th>
</tr>
<?php echo $inner_html; ?>
<?php if ($after !== '') : ?>
<tr class="<?php echo esc_attr(implode(' ', $after_classes)); ?>" data-kplr-section-id="<?php echo esc_attr($section_id); ?>-after"><td colspan="2"><?php echo $after; ?></td></tr>
<?php endif; ?>
<?php
return new ComponentRenderResult(
	(string) ob_get_clean(),
	component_type: 'layout_wrapper'
);
