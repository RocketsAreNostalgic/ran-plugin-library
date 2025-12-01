<?php
/**
 * Template for rendering a fieldset in UserSettings table-based layout.
 *
 * Matches WordPress core pattern (e.g., Admin Color Scheme in profile):
 * - Fieldset is inside <td>, not wrapping the row
 * - Legend uses screen-reader-text (visually hidden, accessible)
 * - <th> provides visible label, legend duplicates for accessibility
 * - No nested table - content rendered directly in fieldset
 *
 * Order: Title (th) → Legend (sr-only) → Description → Before → Content → After
 *
 * @var array{
 *     group_id: string,
 *     title: string,
 *     description?: string,
 *     content: string,
 *     before?: string,
 *     after?: string,
 *     style?: string,
 *     required?: bool
 * } $context
 */

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

$group_id    = isset($context['group_id']) ? (string) $context['group_id'] : '';
$title       = isset($context['title']) ? (string) $context['title'] : '';
$description = isset($context['description']) ? (string) $context['description'] : '';
$inner_html  = isset($context['inner_html']) ? (string) $context['inner_html'] : '';
$style       = isset($context['style']) ? (string) $context['style'] : 'bordered';
$required    = isset($context['required']) && $context['required'];

$before = (string) ($context['before'] ?? '');
$after  = (string) ($context['after'] ?? '');

$fieldset_classes = array(
	'kepler-fieldset-group',
	'kepler-fieldset-group--' . $style,
	$required ? 'kepler-fieldset-group--required' : '',
);

ob_start();
?>
<tr class="fieldset-row" data-group-id="<?php echo esc_attr($group_id); ?>-heading">
	<td scope="row" colspan="2">
		<h4 class="group-title"><?php echo esc_html($title); ?></h4>
		<?php echo $required ? '<span class="required">*</span>' : ''; ?>
	</td>
</tr>
<tr class="fieldset-wrapping-row" data-group-id="<?php echo esc_attr($group_id); ?>-fieldset">
	<td colspan="2">
		<fieldset class="<?php echo esc_attr(implode(' ', array_filter($fieldset_classes))); ?>">
			<?php if ($title !== '') : ?>
				<legend class="screen-reader-text"><span><?php echo esc_html($title); ?></span></legend>
			<?php endif; ?>
			<?php if ($description !== '') : ?>
				<p class="kepler-fieldset-group__description description"><?php echo esc_html($description); ?></p>
			<?php endif; ?>
			<?php echo $before; ?>
			<table class="form-table" role="presentation">
				<tbody>
					<?php echo $inner_html; ?>
				</tbody>
			</table>
			<?php echo $after; ?>
		</fieldset>
	</td>
</tr>
<?php
return new ComponentRenderResult(
	markup: (string) ob_get_clean(),
	component_type: 'layout_wrapper'
);
