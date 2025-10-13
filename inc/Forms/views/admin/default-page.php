<?php
/**
 * Default admin settings page view.
 *
 * Expected $context keys:
 * - page_title: string
 * - group: string
 * - render_fields: callable
 * - render_sections: callable
 * - render_submit: callable
 */

$page_title      = $context['page_title']      ?? 'Settings';
$render_fields   = $context['render_fields']   ?? null;
$render_sections = $context['render_sections'] ?? null;
$render_submit   = $context['render_submit']   ?? null;

$fields_html = '';
if (is_callable($render_fields)) {
	ob_start();
	$render_fields();
	$fields_html = ob_get_clean();
}

$sections_html = '';
if (is_callable($render_sections)) {
	ob_start();
	$render_sections();
	$sections_html = ob_get_clean();
}

$submit_html = '';
if (is_callable($render_submit)) {
	ob_start();
	$render_submit();
	$submit_html = ob_get_clean();
}

ob_start();
?>
<div class="wrap">
	<h1><?php echo esc_html($page_title); ?></h1>
	<form method="post" action="options.php">
		<?php echo $fields_html; ?>
		<?php echo $sections_html; ?>
		<?php echo $submit_html; ?>
	</form>
</div>
<?php
return (string) ob_get_clean();
