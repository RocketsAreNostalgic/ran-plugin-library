<?php
/**
 * Default template for a collection of user settings, on the user profile page.
 *
 * @var array $context
 */

if (!isset($context) || !is_array($context)) {
	return '';
}

$renderer = $context['render'] ?? null;
$title    = isset($context['collection_title']) ? (string) $context['collection_title'] : '';

ob_start();
?>
<?php if ($title !== '') : ?>
	<h2><?php echo esc_html($title); ?></h2>
<?php endif; ?>
<?php if (is_callable($renderer)) : ?>
	<?php echo $renderer(); ?>
<?php endif; ?>
<?php
return (string) ob_get_clean();
