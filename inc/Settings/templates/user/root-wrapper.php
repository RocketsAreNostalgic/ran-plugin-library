<?php
/**
 * Template for wrapping a root collection of user settings in profile context.
 * This template provides the table structure required for WordPress profile pages.
 *
 * @var array{
 *     collection_title?: string,
 *     content: string,
 *     render?: callable
 * } $context
 */

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

if (!isset($context['content']) || $context['content'] === '') {
	return new ComponentRenderResult('');
}

$title   = isset($context['collection_title']) ? (string) $context['collection_title'] : '';
$content = (string) $context['content'];

ob_start();
?>
<?php if ($title !== '') : ?>
	<h2><?php echo esc_html($title); ?></h2>
<?php endif; ?>
<table class="form-table" role="presentation">
	<?php echo $content; ?>
</table>
<?php
return new ComponentRenderResult((string) ob_get_clean());
