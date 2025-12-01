<?php
/**
 * Template for wrapping a root collection of user settings in profile context.
 * This template provides the table structure required for WordPress profile pages.
 *
 * @var array{
 *     collection_title?: string,
 *     heading?: string,
 *     description?: string,
 *     inner_html: string,
 *     before?: string,
 *     after?: string,
 *     render?: callable
 * } $context
 */

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

if (!isset($context['inner_html']) || $context['inner_html'] === '') {
	return new ComponentRenderResult('');
}

$title         = isset($context['heading']) ? (string) $context['heading'] : (isset($context['collection_title']) ? (string) $context['collection_title'] : '');
$description   = isset($context['description']) ? (string) $context['description'] : '';
$collection_id = preg_replace('/[^a-z0-9]/i', '-', strtolower($title));
$style         = isset($context['style']) ? (string) $context['style'] : '';
$inner_html    = (string) $context['inner_html'];

$before = (string) ($context['before'] ?? '');
$after  = (string) ($context['after'] ?? '');

ob_start();
?>
<div class="kepler-user-settings <?php echo esc_attr($style); ?>" data-kepler-collection-id="<?php echo esc_attr($collection_id); ?>">
<?php if ($title !== '') : ?>
	<h2><?php echo esc_html($title); ?></h2>
<?php endif; ?>
<?php if ($description !== '') : ?>
	<p class="description"><?php echo esc_html($description); ?></p>
<?php endif; ?>
<?php echo $before; ?>
<table class="form-table" role="presentation">
	<?php echo $inner_html; ?>
</table>
<?php echo $after; ?>
</div>
<?php
return new ComponentRenderResult((string) ob_get_clean());
