<?php
/**
 * Template for wrapping a root collection of user settings in profile context.
 * This template provides the table structure required for WordPress profile pages.
 *
 * @var array{
 *     collection_title?: string,
 *     heading?: string,
 *     description?: string,
 *     content: string,
 *     before?: string,
 *     after?: string,
 *     render?: callable
 * } $context
 */

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

if (!isset($context['content']) || $context['content'] === '') {
	return new ComponentRenderResult('');
}

$title       = isset($context['heading']) ? (string) $context['heading'] : (isset($context['collection_title']) ? (string) $context['collection_title'] : '');
$description = isset($context['description']) ? (string) $context['description'] : '';
$content     = (string) $context['content'];

$before = (string) ($context['before'] ?? '');
$after  = (string) ($context['after'] ?? '');

ob_start();
?>
<?php if ($title !== '') : ?>
	<h2><?php echo esc_html($title); ?></h2>
<?php endif; ?>
<?php if ($description !== '') : ?>
	<p class="description"><?php echo esc_html($description); ?></p>
<?php endif; ?>
<?php echo $before; ?>
<table class="form-table" role="presentation">
	<?php echo $content; ?>
</table>
<?php
echo $after;
return new ComponentRenderResult((string) ob_get_clean());
