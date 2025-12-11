<?php
/**
 * Template for rendering a section in user profile context.
 *
 * Mirrors the group/fieldset pattern: content is wrapped in a single row with an internal table,
 * allowing before/after hooks to render outside the table structure for better layout control.
 *
 * Structure:
 * - Optional title row (h3 heading)
 * - Wrapper row containing: before → description → inner table (fields) → after
 *
 * @var array{
 *     section_id?: string,
 *     title?: string,
 *     description?: string,
 *     inner_html: string,
 *     before?: string,
 *     after?: string,
 *     style?: string
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
	$section_classes[] = 'kplr-section--' . $style;
	$section_classes[] = $style;
}

$title_row_classes = array('kplr-section__title-row');
if ($style !== '') {
	$title_row_classes[] = $style;
}

$wrapper_row_classes = array('kplr-section__wrapper-row');
if ($style !== '') {
	$wrapper_row_classes[] = $style;
}

ob_start();
?>
<?php if ($title !== '') : ?>
<tr class="<?php echo esc_attr(implode(' ', $title_row_classes)); ?>" data-kplr-section-id="<?php echo esc_attr($section_id); ?>-heading">
	<th scope="row" colspan="2">
		<h3 class="kplr-section__title"><?php echo esc_html($title); ?></h3>
	</th>
</tr>
<?php endif; ?>
<tr class="<?php echo esc_attr(implode(' ', $wrapper_row_classes)); ?>" data-kplr-section-id="<?php echo esc_attr($section_id); ?>">
	<td colspan="2">
		<div class="<?php echo esc_attr(implode(' ', $section_classes)); ?>">
			<?php echo $before; ?>
			<?php if ($description !== '') : ?>
				<p class="kplr-section__description"><?php echo wp_kses_post($description); ?></p>
			<?php endif; ?>
			<table class="form-table" role="presentation">
				<tbody>
					<?php echo $inner_html; ?>
				</tbody>
			</table>
			<?php echo $after; ?>
		</div>
	</td>
</tr>
<?php
return new ComponentRenderResult(
	markup: (string) ob_get_clean()
);
