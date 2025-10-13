<?php
/**
 * Template for rendering a single field row in user profile sections.
 *
 * @var array{
 *     label:string,
 *     content:string
 * } $context
 */

if (!isset($context['content']) || $context['content'] === '') {
	return '';
}

$label   = isset($context['label']) ? (string) $context['label'] : '';
$content = (string) $context['content'];

ob_start();
?>
<tr>
	<th scope="row">
		<?php if ($label !== '') : ?>
			<label><?php echo esc_html($label); ?></label>
		<?php endif; ?>
	</th>
	<td><?php echo $content; ?></td>
</tr>
<?php
return (string) ob_get_clean();
