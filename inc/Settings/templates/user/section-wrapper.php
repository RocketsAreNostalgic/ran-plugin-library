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
$content     = isset($context['content']) ? (string) $context['content'] : '';

$before = (string) ($context['before'] ?? '');
$after  = (string) ($context['after'] ?? '');

ob_start();
?>
<tr data-section-id="<?php echo esc_attr($section_id); ?>"><td colspan="2">
<?php if ($title !== '') : ?>
	<h3><?php echo esc_html($title); ?></h3>
<?php endif; ?>
<?php if ($description !== '') : ?>
	<p class="description"><?php echo wp_kses_post($description); ?></p>
<?php endif; ?>
<?php echo $before; ?>
</td></tr>
<?php echo $content; ?>
<tr data-section-id="<?php echo esc_attr($section_id); ?>-after"><td colspan="2"><?php echo $after; ?></td></tr>
<?php
return new ComponentRenderResult(
	(string) ob_get_clean(),
	component_type: 'layout_wrapper'
);
