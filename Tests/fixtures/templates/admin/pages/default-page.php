<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

$heading        = $context['heading']                  ?? 'Settings';
$description    = $context['page_meta']['description'] ?? ($context['description'] ?? '');
$settings_group = $context['group']                    ?? ($context['settings_group'] ?? '');
$content        = $context['inner_html']               ?? '';

ob_start();
?>
<div class="wrap admin-settings-page">
	<?php if ($heading !== ''): ?>
		<h1><?php echo esc_html($heading); ?></h1>
	<?php endif; ?>

	<?php if ($description !== ''): ?>
		<p class="description"><?php echo esc_html($description); ?></p>
	<?php endif; ?>

	<form method="post" action="options.php">
		<?php
		if ($settings_group !== '' && function_exists('settings_fields')) {
			settings_fields($settings_group);
		}
?>

		<div class="admin-page-content">
			<?php echo $content; ?>
		</div>

		<?php
$renderSubmit = $context['render_submit'] ?? null;
if (is_callable($renderSubmit)) {
	echo (string) $renderSubmit();
}
?>
	</form>
</div>
<?php

return new ComponentRenderResult(
	markup: (string) ob_get_clean()
);
