<?php
/**
 * Template for rendering a group in UserSettings table-based layout.
 *
 * Mirrors the fieldset pattern: content is wrapped in a single row with an internal table,
 * allowing before/after hooks to render outside the table structure for better layout control.
 *
 * Structure:
 * - Optional title row (h4 heading)
 * - Wrapper row containing: before → description → inner table (fields) → after
 *
 * @var array{
 *     group_id: string,
 *     title: string,
 *     description?: string,
 *     inner_html: string,
 *     before?: string,
 *     after?: string,
 *     style?: string
 * } $context
 */

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

$group_id    = isset($context['group_id']) ? (string) $context['group_id'] : '';
$title       = isset($context['title']) ? (string) $context['title'] : '';
$description = isset($context['description']) ? (string) $context['description'] : '';
$inner_html  = isset($context['inner_html']) ? (string) $context['inner_html'] : '';
$style       = trim((string) ($context['style'] ?? ''));

$before = (string) ($context['before'] ?? '');
$after  = (string) ($context['after'] ?? '');

$group_classes = array('kplr-group');
if ($style !== '') {
	$group_classes[] = 'kplr-group--' . $style;
	$group_classes[] = $style;
}

$title_row_classes = array('kplr-group__title-row');
if ($style !== '') {
	$title_row_classes[] = $style;
}

$wrapper_row_classes = array('kplr-group__wrapper-row');
if ($style !== '') {
	$wrapper_row_classes[] = $style;
}

ob_start();
?>
<?php if ($title !== '') : ?>
<tr class="<?php echo esc_attr(implode(' ', $title_row_classes)); ?>" data-kplr-group-id="<?php echo esc_attr($group_id); ?>-heading">
	<th scope="row" colspan="2">
		<h4 class="kplr-group__title"><?php echo esc_html($title); ?></h4>
	</th>
</tr>
<?php endif; ?>
<tr class="<?php echo esc_attr(implode(' ', $wrapper_row_classes)); ?>" data-kplr-group-id="<?php echo esc_attr($group_id); ?>">
	<td colspan="2">
		<div class="<?php echo esc_attr(implode(' ', $group_classes)); ?>">
			<?php echo $before; ?>
			<?php if ($description !== '') : ?>
				<p class="kplr-group__description"><?php echo esc_html($description); ?></p>
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
