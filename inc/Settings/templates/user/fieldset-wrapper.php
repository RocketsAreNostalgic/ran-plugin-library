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
 *     title?: string,
 *     description?: string,
 *     inner_html: string,
 *     before?: string,
 *     after?: string,
 *     style?: string,
 *     form?: string,
 *     name?: string,
 *     disabled?: bool
 * } $context
 */

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

$group_id    = isset($context['group_id']) ? (string) $context['group_id'] : '';
$title       = isset($context['title']) ? (string) $context['title'] : '';
$description = isset($context['description']) ? (string) $context['description'] : '';
$inner_html  = isset($context['inner_html']) ? (string) $context['inner_html'] : '';
$style       = isset($context['style']) ? (string) $context['style'] : '';
$form        = isset($context['form']) ? (string) $context['form'] : '';
$name        = isset($context['name']) ? (string) $context['name'] : '';
$disabled    = isset($context['disabled']) && $context['disabled'];

$before = (string) ($context['before'] ?? '');
$after  = (string) ($context['after'] ?? '');

$fieldset_classes = array('kplr-fieldset');
if ($style !== '') {
	$fieldset_classes[] = 'kplr-fieldset--' . $style;
	$fieldset_classes[] = $style;
}
if ($disabled) {
	$fieldset_classes[] = 'kplr-fieldset--disabled';
}

$title_row_classes = array('kplr-fieldset__title-row');
if ($style !== '') {
	$title_row_classes[] = $style;
}

$wrapper_row_classes = array('kplr-fieldset__wrapper-row');
if ($style !== '') {
	$wrapper_row_classes[] = $style;
}

// Build fieldset attributes
$fieldset_attrs = array(
    'class' => implode(' ', $fieldset_classes),
);
if ($form !== '') {
	$fieldset_attrs['form'] = $form;
}
if ($name !== '') {
	$fieldset_attrs['name'] = $name;
}
if ($disabled) {
	$fieldset_attrs['disabled'] = 'disabled';
}

ob_start();
?>
<?php if ($title !== '') : ?>
<tr class="<?php echo esc_attr(implode(' ', $title_row_classes)); ?>" data-kplr-group-id="<?php echo esc_attr($group_id); ?>-heading">
	<th scope="row" colspan="2">
		<h4 class="kplr-fieldset__title"><?php echo esc_html($title); ?></h4>
	</th>
</tr>
<?php endif; ?>
<tr class="<?php echo esc_attr(implode(' ', $wrapper_row_classes)); ?>" data-kplr-group-id="<?php echo esc_attr($group_id); ?>">
	<td colspan="2">
		<fieldset <?php foreach ($fieldset_attrs as $attr => $val) : ?><?php echo esc_attr($attr); ?>="<?php echo esc_attr($val); ?>" <?php endforeach; ?>>
			<?php if ($title !== '') : ?>
				<legend class="kplr-fieldset__legend screen-reader-text"><span><?php echo esc_html($title); ?></span></legend>
			<?php endif; ?>
			<?php if ($description !== '') : ?>
				<p class="kplr-fieldset__description"><?php echo esc_html($description); ?></p>
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
	markup: (string) ob_get_clean()
);
