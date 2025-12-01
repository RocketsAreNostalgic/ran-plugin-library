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

$before = (string) ($context['before'] ?? '');
$after  = (string) ($context['after'] ?? '');

ob_start();

// 1. Title row (if title exists)
if ($title !== '') :
	?>
<tr class="group-header-row" data-group-id="<?php echo esc_attr($group_id); ?>">
	<th colspan="2"><h4 class="group-title"><?php echo esc_html($title); ?></h4></th>
</tr>
<?php endif; ?>
<?php if ($description !== '') : ?>
<tr class="group-description-row">
	<th colspan="2"><p class="description"><?php echo esc_html($description); ?></p></th>
</tr>
<?php endif; ?>
<?php // 2. Before hook row (if before exists)
if ($before !== '') : ?>
<tr class="group-before-row">
	<td colspan="2"><?php echo $before; ?></td>
</tr>
<?php endif; ?>
<?php
// 3. Inner HTML (fields - already formatted as table rows)
echo $inner_html;
?>
<?php // 4. After hook row (if after exists)
if ($after !== '') : ?>
<tr class="group-after-row">
	<td colspan="2"><?php echo $after; ?></td>
</tr>
<?php endif;

return new ComponentRenderResult(
	markup: (string) ob_get_clean(),
	component_type: 'layout_wrapper'
);
