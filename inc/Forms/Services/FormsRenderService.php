<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Services;

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Forms\Renderer\FormElementRenderer;
use Ran\PluginLib\Forms\FormsServiceSession;
use Ran\PluginLib\Forms\ErrorNoticeRenderer;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\Component\ComponentLoader;

class FormsRenderService implements FormsRenderServiceInterface {
	/** @var callable():void */
	private $start_form_session;

	/** @var callable():?FormsServiceSession */
	private $get_form_session;

	/** @var callable():string */
	private $get_section_template;

	/**
	 * @param callable():void $start_form_session
	 * @param callable():?FormsServiceSession $get_form_session
	 * @param callable():string $get_section_template
	 */
	public function __construct(
		private FormsStateStoreInterface $state_store,
		private Logger $logger,
		private ComponentLoader $views,
		private FormElementRenderer $field_renderer,
		private string $main_option,
		callable $start_form_session,
		callable $get_form_session,
		callable $get_section_template
	) {
		$this->start_form_session   = $start_form_session;
		$this->get_form_session     = $get_form_session;
		$this->get_section_template = $get_section_template;
	}

	private function _ensure_session(): FormsServiceSession {
		$session = ($this->get_form_session)();
		if (!$session instanceof FormsServiceSession) {
			($this->start_form_session)();
			$session = ($this->get_form_session)();
		}
		if (!$session instanceof FormsServiceSession) {
			throw new \RuntimeException('FormsRenderService: expected active FormsServiceSession.');
		}
		return $session;
	}

	public function finalize_render(string $container_id, array $payload, array $element_context = array()): void {
		$session  = $this->_ensure_session();
		$callback = $session->get_root_template_callback($container_id);
		if ($callback !== null) {
			ob_start();
			$callback($payload);
			echo (string) ob_get_clean();
		} else {
			echo $session->render_element('root-wrapper', $payload, array(
				'root_id' => $container_id,
				...$element_context,
			));
		}
		$session->enqueue_assets();
	}

	public function render_default_sections_wrapper(string $id_slug, array $sections, array $values): string {
		$groups_map = $this->state_store->get_groups_map($id_slug);
		$fields_map = $this->state_store->get_fields_map($id_slug);

		$session = $this->_ensure_session();

		$all_sections_markup = '';

		foreach ($sections as $section_id => $meta) {
			$groups = $groups_map[$section_id] ?? array();
			$fields = $fields_map[$section_id] ?? array();

			uasort($groups, function ($a, $b) {
				return ($a['order'] <=> $b['order']) ?: ($a['index'] <=> $b['index']);
			});
			usort($fields, function ($a, $b) {
				return ($a['order'] <=> $b['order']) ?: ($a['index'] <=> $b['index']);
			});

			$section_content = '';

			foreach ($groups as $group) {
				$group_fields = $group['fields'];
				usort($group_fields, function ($a, $b) {
					return ($a['order'] <=> $b['order']) ?: ($a['index'] <=> $b['index']);
				});

				$group_fields_content = '';
				foreach ($group_fields as $group_field) {
					if (($group_field['component'] ?? '') === '_raw_html') {
						$group_fields_content .= $this->render_raw_html_content($group_field, array(
							'container_id' => $id_slug,
							'section_id'   => $section_id,
							'group_id'     => $group['group_id'] ?? '',
							'values'       => $values,
						));
						continue;
					}

					if (($group_field['component'] ?? '') === '_hr') {
						$group_fields_content .= $this->render_hr_content($group_field, array(
							'container_id' => $id_slug,
							'section_id'   => $section_id,
							'group_id'     => $group['group_id'] ?? '',
							'values'       => $values,
						));
						continue;
					}

					$field_item = array(
						'field'  => $group_field,
						'before' => $this->render_callback_output($group_field['before'] ?? null, array(
							'field_id'     => $group_field['id'] ?? '',
							'container_id' => $id_slug,
							'section_id'   => $section_id,
							'group_id'     => $group['group_id'] ?? '',
							'values'       => $values,
						)),
						'after' => $this->render_callback_output($group_field['after'] ?? null, array(
							'field_id'     => $group_field['id'] ?? '',
							'container_id' => $id_slug,
							'section_id'   => $section_id,
							'group_id'     => $group['group_id'] ?? '',
							'values'       => $values,
						)),
						'group_type' => $group['type'] ?? 'group',
					);
					$group_fields_content .= $this->render_default_field_wrapper($field_item, $values);
				}

				$group_before = $this->render_callback_output($group['before'] ?? null, array(
					'group_id'     => $group['group_id'] ?? '',
					'section_id'   => $section_id,
					'container_id' => $id_slug,
					'fields'       => $group_fields,
					'values'       => $values,
				)) ?? '';
				$group_after = $this->render_callback_output($group['after'] ?? null, array(
					'group_id'     => $group['group_id'] ?? '',
					'section_id'   => $section_id,
					'container_id' => $id_slug,
					'fields'       => $group_fields,
					'values'       => $values,
				)) ?? '';

				$section_content .= $this->render_group_wrapper($group, $group_fields_content, $group_before, $group_after, $values);
			}

			foreach ($fields as $field) {
				if (($field['component'] ?? '') === '_raw_html') {
					$section_content .= $this->render_raw_html_content($field, array(
						'container_id' => $id_slug,
						'section_id'   => $section_id,
						'values'       => $values,
					));
					continue;
				}

				if (($field['component'] ?? '') === '_hr') {
					$section_content .= $this->render_hr_content($field, array(
						'container_id' => $id_slug,
						'section_id'   => $section_id,
						'values'       => $values,
					));
					continue;
				}

				$field_item = array(
					'field'  => $field,
					'before' => $this->render_callback_output($field['before'] ?? null, array(
						'field_id'     => $field['id'] ?? '',
						'container_id' => $id_slug,
						'section_id'   => $section_id,
						'values'       => $values,
					)),
					'after' => $this->render_callback_output($field['after'] ?? null, array(
						'field_id'     => $field['id'] ?? '',
						'container_id' => $id_slug,
						'section_id'   => $section_id,
						'values'       => $values,
					)),
				);
				$section_content .= $this->render_default_field_wrapper($field_item, $values);
			}

			$section_style   = trim((string) ($meta['style'] ?? ''));
			$description_cb  = $meta['description_cb'] ?? null;
			$section_context = array(
				'section_id'  => $section_id,
				'title'       => (string) $meta['title'],
				'description' => is_callable($description_cb) ? (string) ($description_cb)() : (string) ($description_cb ?? ''),
				'inner_html'  => $section_content,
				'before'      => $this->render_callback_output($meta['before'] ?? null, array(
					'container_id' => $id_slug,
					'section_id'   => $section_id,
					'values'       => $values,
				)) ?? '',
				'after' => $this->render_callback_output($meta['after'] ?? null, array(
					'container_id' => $id_slug,
					'section_id'   => $section_id,
					'values'       => $values,
				)) ?? '',
				'style' => trim($section_style),
			);

			$section_template = (string) ($this->get_section_template)();
			$sectionComponent = $this->views->render($section_template, $section_context);

			if (!($sectionComponent instanceof ComponentRenderResult)) {
				throw new \UnexpectedValueException('Section template must return a ComponentRenderResult instance.');
			}

			$session->ingest_component_result(
				$sectionComponent,
				'render_section',
				null
			);

			$all_sections_markup .= $sectionComponent->markup;
		}

		return $all_sections_markup;
	}

	public function render_group_wrapper(array $group, string $fields_content, string $before_content, string $after_content, array $values): string {
		$group_id = $group['group_id'] ?? '';
		$title    = $group['title']    ?? '';
		$style    = trim((string) ($group['style'] ?? ''));

		$group_context = array(
			'group_id'    => $group_id,
			'title'       => $title,
			'description' => '',
			'inner_html'  => $fields_content,
			'before'      => $before_content,
			'after'       => $after_content,
			'layout'      => 'vertical',
			'spacing'     => 'normal',
			'style'       => $style,
			'values'      => $values,
		);

		try {
			$session = $this->_ensure_session();
			$result  = $session->render_component('group-wrapper', $group_context);
			if ($result !== '') {
				return $result;
			}
		} catch (\Throwable $e) {
			$this->logger->warning('FormsBaseTrait: Group wrapper template failed, using fallback', array(
				'group_id'          => $group_id,
				'exception_message' => $e->getMessage(),
			));
		}

		$group_classes = array('group-wrapper');
		if ($style !== '') {
			$group_classes[] = $style;
		}
		$output = '';
		if ($title !== '') {
			$output .= '<div class="' . esc_attr(implode(' ', $group_classes)) . '" data-group-id="' . esc_attr($group_id) . '">';
			$output .= '<h4 class="group-wrapper__title">' . esc_html($title) . '</h4>';
			$output .= '<div class="group-wrapper__content">';
		}
		$output .= $before_content;
		$output .= $fields_content;
		$output .= $after_content;
		if ($title !== '') {
			$output .= '</div></div>';
		}

		return $output;
	}

	public function render_callback_output(?callable $callback, array $context): ?string {
		if ($callback === null) {
			return null;
		}

		if (!is_callable($callback)) {
			$this->logger->warning('FormsBaseTrait: Callback provided is not callable', array('context_keys' => array_keys($context)));
			return null;
		}

		$context_keys = array_keys($context);

		try {
			$result         = (string) $callback($context);
			$result_length  = strlen($result);
			$preview_length = 120;
			$this->logger->debug('FormsBaseTrait: Callback executed', array(
				'context_keys'     => $context_keys,
				'result_length'    => $result_length,
				'result_preview'   => $preview_length >= $result_length ? $result : substr($result, 0, $preview_length),
				'result_truncated' => $result_length > $preview_length,
			));
			return $result;
		} catch (\Throwable $e) {
			$this->logger->error('FormsBaseTrait: Callback execution failed', array(
				'context_keys'      => $context_keys,
				'exception_class'   => get_class($e),
				'exception_message' => $e->getMessage(),
			));
			return null;
		}
	}

	public function render_default_field_wrapper(array $field_item, array $values): string {
		$field = $field_item['field'] ?? $field_item;
		if (empty($field)) {
			return '';
		}

		$field_id  = isset($field['id']) ? (string) $field['id'] : '';
		$label     = isset($field['label']) ? (string) $field['label'] : '';
		$component = isset($field['component']) && is_string($field['component']) ? trim($field['component']) : '';
		if (ErrorNoticeRenderer::isVerboseDebug()) {
			$this->logger->debug('forms.default_field.render', array(
				'field_id'  => $field_id,
				'component' => $component,
			));
		}

		if ($component === '') {
			$this->logger->error(FormsRenderService::class . ': field missing component metadata.', array('field' => $field_id));
			throw new \InvalidArgumentException(sprintf(FormsRenderService::class . ': field "%s" requires a component alias.', $field_id ?: 'unknown'));
		}

		$component_context = $field['component_context'] ?? array();
		if (!is_array($component_context)) {
			$this->logger->error( FormsRenderService::class . ': field provided a non-array component_context.', array('field' => $field_id));
			throw new \InvalidArgumentException(sprintf(FormsRenderService::class . ': field "%s" must provide an array component_context.', $field_id ?: 'unknown'));
		}

		$field['field_id']          = $field_id;
		$field['component']         = $component;
		$field['label']             = $label;
		$field['component_context'] = $component_context;

		if (!isset($field['name']) && $field_id !== '') {
			$field['name'] = $this->main_option . '[' . $field_id . ']';
		}

		try {
			$session = $this->_ensure_session();

			$extras = array();
			if (array_key_exists('before', $field_item) && $field_item['before'] !== null) {
				$extras['before'] = (string) $field_item['before'];
			}
			if (array_key_exists('after', $field_item) && $field_item['after'] !== null) {
				$extras['after'] = (string) $field_item['after'];
			}

			$field_context = $this->field_renderer->prepare_field_context(
				$field,
				$values,
				$extras
			);

			$group_type  = $field_item['group_type'] ?? 'group';
			$wrapper_key = $group_type === 'fieldset' ? 'fieldset-field-wrapper' : 'field-wrapper';

			return $this->field_renderer->render_field_with_wrapper(
				$component,
				$field_id,
				$label,
				$field_context,
				$wrapper_key,
				$wrapper_key,
				$session
			);
		} catch (\Throwable $e) {
			$this->logger->error(FormsRenderService::class . ': Field rendering failed', array(
				'field_id'          => $field_id,
				'component'         => $component,
				'exception_class'   => get_class($e),
				'exception_code'    => $e->getCode(),
				'exception_message' => $e->getMessage(),
			));
			return $this->render_default_field_wrapper_warning($e->getMessage());
		}
	}

	public function render_raw_html_content(array $field, array $context): string {
		$content = $field['component_context']['content'] ?? '';

		if (is_callable($content)) {
			return (string) $content($context);
		}

		return (string) $content;
	}

	public function render_hr_content(array $field, array $context): string {
		$component_context = $field['component_context'] ?? array();
		$style_classes     = trim($component_context['style'] ?? '');

		$before = '';
		if (isset($field['before']) && is_callable($field['before'])) {
			$before = (string) ($field['before'])($context);
		}

		$after = '';
		if (isset($field['after']) && is_callable($field['after'])) {
			$after = (string) ($field['after'])($context);
		}

		$class_attr = 'kplr-hr' . ($style_classes !== '' ? ' ' . $style_classes : '');

		$hr = '<hr class="' . esc_attr($class_attr) . '">';

		return $before . $hr . $after;
	}

	public function render_default_field_wrapper_warning(string $message): string {
		$session         = $this->_ensure_session();
		$display_message = ErrorNoticeRenderer::getFieldErrorMessage($message);

		return $session->render_element('field-wrapper', array(
			'field_id'            => 'error',
			'label'               => 'Error',
			'inner_html'          => '&nbsp;',
			'validation_warnings' => array($display_message),
			'field_type'          => 'error',
		));
	}

	public function container_has_file_uploads(string $container_id): bool {
		$container_fields = $this->state_store->get_fields_map($container_id);
		foreach ($container_fields as $section_id => $fields) {
			foreach ($fields as $field) {
				$component = $field['component'] ?? '';
				if ($component === 'fields.file-upload') {
					return true;
				}
			}
		}

		$container_groups = $this->state_store->get_groups_map($container_id);
		foreach ($container_groups as $section_id => $groups) {
			foreach ($groups as $group_id => $group) {
				$group_fields = $group['fields'] ?? array();
				foreach ($group_fields as $field) {
					$component = $field['component'] ?? '';
					if ($component === 'fields.file-upload') {
						return true;
					}
				}
			}
		}

		return false;
	}
}
