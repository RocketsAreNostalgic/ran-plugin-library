<?php
/**
 * Template for rendering a section in user profile context.
 * This template handles table-based constraints for WordPress profile pages.
 *
 * @var array{
 *     title?: string,
 *     description?: string,
 *     content: string,
 *     fields?: array
 * } $context
 */

if (!isset($context['content']) || $context['content'] === '') {
	return '';
}

$title       = isset($context['title']) ? (string) $context['title'] : '';
$description = isset($context['description']) ? (string) $context['description'] : '';
$content     = (string) $context['content'];

ob_start();
?>
<?php if ($title !== '') : ?>
	<tr><th colspan="2"><?php echo esc_html($title); ?></th></tr>
<?php endif; ?>

<?php if ($description !== '') : ?>
	<tr><td colspan="2"><?php echo wp_kses_post($description); ?></td></tr>
<?php endif; ?>

<?php echo $content; ?>
<?php
return (string) ob_get_clean();
