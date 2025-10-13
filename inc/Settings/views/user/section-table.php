<?php
/**
 * Generic table view for settings sections.
 *
 * Expected $context keys:
 * - sections: array<int, array{title:string, description_cb:?callable, items:array<int, array<string,mixed>>}>
 * - row_renderer: callable(array $field): string (returns table row markup)
 * - group_renderer: callable(array $group): string (returns HTML for group rows)
 */

$sections       = $context['sections']       ?? array();
$row_renderer   = $context['row_renderer']   ?? null;
$group_renderer = $context['group_renderer'] ?? null;

if (!is_array($sections) || !is_callable($row_renderer)) {
	return '';
}

ob_start();
?>
<table class="form-table" role="presentation">
	<?php foreach ($sections as $section) : ?>
		<?php if (!empty($section['title'])) : ?>
			<tr><th colspan="2"><?php echo esc_html($section['title']); ?></th></tr>
		<?php endif; ?>

		<?php if (isset($section['description_cb']) && is_callable($section['description_cb'])) : ?>
			<tr><td colspan="2"><?php ($section['description_cb'])(); ?></td></tr>
		<?php endif; ?>

		<?php foreach ($section['items'] as $item) : ?>
			<?php if (($item['type'] ?? '') === 'group') : ?>
				<?php if (is_callable($group_renderer)) : ?>
					<?php echo $group_renderer($item); ?>
				<?php endif; ?>
				<?php continue; ?>
			<?php endif; ?>

			<?php echo $row_renderer($item['field'] ?? array()); ?>
		<?php endforeach; ?>
	<?php endforeach; ?>
</table>
<?php
return (string) ob_get_clean();
