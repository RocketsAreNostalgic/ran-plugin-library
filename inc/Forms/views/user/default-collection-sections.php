<?php
/**
 * Default template for rendering user settings sections.
 *
 * @var array $context {
 *     @type array<int, array{title:string, description_cb:?callable, items:array<int, array<string,mixed>>}> $sections
 *     @type array $values Current option values
 *     @type WP_User|null $profile_user Profile user context
 * }
 */

if (!isset($context['sections']) || !is_array($context['sections'])) {
	return '';
}

$sections     = $context['sections'];
$values       = $context['values']       ?? array();
$profile_user = $context['profile_user'] ?? null;

ob_start();
?>
<table class="form-table" role="presentation">
	<?php foreach ($sections as $section) : ?>
		<?php if (!empty($section['title'])) : ?>
			<tr><th colspan="2"><?php echo esc_html($section['title']); ?></th></tr>
		<?php endif; ?>

		<?php
		if (isset($section['description_cb']) && is_callable($section['description_cb'])) {
			ob_start();
			($section['description_cb'])();
			$description_html = ob_get_clean();
			if ($description_html !== '') {
				?>
				<tr><td colspan="2"><?php echo $description_html; ?></td></tr>
				<?php
			}
		}
		?>

		<?php foreach ($section['items'] as $item) : ?>
			<?php if (($item['type'] ?? '') === 'group') : ?>
				<?php
				$before_html = '';
				if (isset($item['before']) && is_callable($item['before'])) {
					ob_start();
					($item['before'])();
					$before_html = ob_get_clean();
				}
				if ($before_html !== '') {
					?>
					<tr><td colspan="2"><?php echo $before_html; ?></td></tr>
					<?php
				}

				foreach ($item['fields'] as $field) {
					ob_start();
					if (isset($field['render']) && is_callable($field['render'])) {
						($field['render'])($profile_user, $values);
					}
					$field_html = ob_get_clean();
					?>
					<tr>
						<th scope="row"><label><?php echo esc_html($field['label'] ?? ''); ?></label></th>
						<td><?php echo $field_html; ?></td>
					</tr>
					<?php
				}

				$after_html = '';
				if (isset($item['after']) && is_callable($item['after'])) {
					ob_start();
					($item['after'])();
					$after_html = ob_get_clean();
				}
				if ($after_html !== '') {
					?>
					<tr><td colspan="2"><?php echo $after_html; ?></td></tr>
					<?php
				}

				continue;
			endif; ?>

			<?php
			$field = $item['field'] ?? array();
			ob_start();
			if (isset($field['render']) && is_callable($field['render'])) {
				($field['render'])($profile_user, $values);
			}
			$field_html = ob_get_clean();
			?>
			<tr>
				<th scope="row"><label><?php echo esc_html($field['label'] ?? ''); ?></label></th>
				<td><?php echo $field_html; ?></td>
			</tr>
		<?php endforeach; ?>
	<?php endforeach; ?>
</table>
<?php
return (string) ob_get_clean();
