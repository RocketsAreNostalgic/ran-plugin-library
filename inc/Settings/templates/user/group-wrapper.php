<?php
/**
 * Template for rendering a group/fieldset in UserSettings table-based layout.
 *
 * Groups and fieldsets are rendered as table rows to maintain WordPress profile page compatibility.
 * Order: Title → Description → Before → Content (fields) → After
 *
 * @var array{
 *     group_id: string,
 *     title: string,
 *     description?: string,
 *     content: string,
 *     before?: string,
 *     after?: string,
 *     style?: string,
 *     type?: string
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

$title_row_classes = array('kplr-group');
if ($style !== '') {
	$title_row_classes[] = $style;
}

$description_row_classes = array('kplr-group__description-row');
if ($style !== '') {
	$description_row_classes[] = $style;
}

$before_row_classes = array('kplr-group__before');
if ($style !== '') {
	$before_row_classes[] = $style;
}

$after_row_classes = array('kplr-group__after');
if ($style !== '') {
	$after_row_classes[] = $style;
}

ob_start();

// 1. Title row (if title exists)
if ($title !== '') :
	?>
<tr class="<?php echo esc_attr(implode(' ', $title_row_classes)); ?>" data-kplr-group-id="<?php echo esc_attr($group_id); ?>">
	<th class="kplr-group__header" colspan="2"><h4 class="kplr-group__title"><?php echo esc_html($title); ?></h4></th>
</tr>
<?php endif; ?>
<?php if ($description !== '') : ?>
<tr class="<?php echo esc_attr(implode(' ', $description_row_classes)); ?>">
	<th colspan="2"><p class="kplr-group__description"><?php echo esc_html($description); ?></p></th>
</tr>
<?php endif; ?>
<?php // 2. Before hook row (if before exists)
if ($before !== '') : ?>
<tr class="<?php echo esc_attr(implode(' ', $before_row_classes)); ?>">
	<td colspan="2"><?php echo $before; ?></td>
</tr>
<?php endif; ?>
<?php
// 3. Inner HTML (fields - already formatted as table rows)
echo $inner_html;
?>
<?php // 4. After hook row (if after exists)
if ($after !== '') : ?>
<tr class="<?php echo esc_attr(implode(' ', $after_row_classes)); ?>">
	<td colspan="2"><?php echo $after; ?></td>
</tr>
<?php endif;

return new ComponentRenderResult(
	markup: (string) ob_get_clean(),
	component_type: 'layout_wrapper'
);
