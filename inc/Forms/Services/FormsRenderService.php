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
		$session = $this->_ensure_session();
		if (!isset($payload['values']) && isset($element_context['values'])) {
			$payload['values'] = $element_context['values'];
		}
		echo $session->render_element('root-wrapper', $payload, array(
			'root_id'      => $container_id,
			'container_id' => $container_id,
			...$element_context,
		));
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
							'field_id'     => isset($group_field['id']) ? (string) $group_field['id'] : '',
							'container_id' => $id_slug,
							'root_id'      => $id_slug,
							'section_id'   => $section_id,
							'group_id'     => $group['group_id'] ?? '',
							'value'        => null,
							'values'       => $values,
						));
						continue;
					}

					if (($group_field['component'] ?? '') === '_hr') {
						$group_fields_content .= $this->render_hr_content($group_field, array(
							'field_id'     => isset($group_field['id']) ? (string) $group_field['id'] : '',
							'container_id' => $id_slug,
							'root_id'      => $id_slug,
							'section_id'   => $section_id,
							'group_id'     => $group['group_id'] ?? '',
							'value'        => null,
							'values'       => $values,
						));
						continue;
					}

					$field_item = array(
						'field'        => $group_field,
						'container_id' => $id_slug,
						'root_id'      => $id_slug,
						'section_id'   => $section_id,
						'group_id'     => $group['group_id'] ?? '',
						'before'       => $this->render_callback_output($group_field['before'] ?? null, array(
							'field_id'     => $group_field['id'] ?? '',
							'container_id' => $id_slug,
							'root_id'      => $id_slug,
							'section_id'   => $section_id,
							'group_id'     => $group['group_id'] ?? '',
							'value'        => isset($group_field['id']) && array_key_exists((string) $group_field['id'], $values) ? $values[(string) $group_field['id']] : null,
							'values'       => $values,
						)),
						'after' => $this->render_callback_output($group_field['after'] ?? null, array(
							'field_id'     => $group_field['id'] ?? '',
							'container_id' => $id_slug,
							'root_id'      => $id_slug,
							'section_id'   => $section_id,
							'group_id'     => $group['group_id'] ?? '',
							'value'        => isset($group_field['id']) && array_key_exists((string) $group_field['id'], $values) ? $values[(string) $group_field['id']] : null,
							'values'       => $values,
						)),
						'group_type' => $group['type'] ?? 'group',
					);
					$group_fields_content .= $this->render_default_field_wrapper($field_item, $values);
				}

				$group_before = $this->render_callback_output($group['before'] ?? null, array(
					'field_id'     => '',
					'container_id' => $id_slug,
					'root_id'      => $id_slug,
					'section_id'   => $section_id,
					'group_id'     => $group['group_id'] ?? '',
					'value'        => null,
					'fields'       => $group_fields,
					'values'       => $values,
				)) ?? '';
				$group_after = $this->render_callback_output($group['after'] ?? null, array(
					'field_id'     => '',
					'container_id' => $id_slug,
					'root_id'      => $id_slug,
					'section_id'   => $section_id,
					'group_id'     => $group['group_id'] ?? '',
					'value'        => null,
					'fields'       => $group_fields,
					'values'       => $values,
				)) ?? '';

				$group['section_id']   = (string) $section_id;
				$group['container_id'] = (string) $id_slug;
				$group['root_id']      = (string) $id_slug;
				$section_content .= $this->render_group_wrapper($group, $group_fields_content, $group_before, $group_after, $values);
			}

			foreach ($fields as $field) {
				if (($field['component'] ?? '') === '_raw_html') {
					$section_content .= $this->render_raw_html_content($field, array(
						'field_id'     => isset($field['id']) ? (string) $field['id'] : '',
						'container_id' => $id_slug,
						'root_id'      => $id_slug,
						'section_id'   => $section_id,
						'group_id'     => '',
						'value'        => null,
						'values'       => $values,
					));
					continue;
				}

				if (($field['component'] ?? '') === '_hr') {
					$section_content .= $this->render_hr_content($field, array(
						'field_id'     => isset($field['id']) ? (string) $field['id'] : '',
						'container_id' => $id_slug,
						'root_id'      => $id_slug,
						'section_id'   => $section_id,
						'group_id'     => '',
						'value'        => null,
						'values'       => $values,
					));
					continue;
				}

				$field_item = array(
					'field'        => $field,
					'container_id' => $id_slug,
					'root_id'      => $id_slug,
					'section_id'   => $section_id,
					'group_id'     => '',
					'before'       => $this->render_callback_output($field['before'] ?? null, array(
						'field_id'     => $field['id'] ?? '',
						'container_id' => $id_slug,
						'root_id'      => $id_slug,
						'section_id'   => $section_id,
						'group_id'     => '',
						'value'        => isset($field['id']) && array_key_exists((string) $field['id'], $values) ? $values[(string) $field['id']] : null,
						'values'       => $values,
					)),
					'after' => $this->render_callback_output($field['after'] ?? null, array(
						'field_id'     => $field['id'] ?? '',
						'container_id' => $id_slug,
						'root_id'      => $id_slug,
						'section_id'   => $section_id,
						'group_id'     => '',
						'value'        => isset($field['id']) && array_key_exists((string) $field['id'], $values) ? $values[(string) $field['id']] : null,
						'values'       => $values,
					)),
				);
				$section_content .= $this->render_default_field_wrapper($field_item, $values);
			}

			$section_style_raw = $meta['style'] ?? '';
			$section_style     = '';
			if (is_callable($section_style_raw)) {
				$style_ctx = array(
					'field_id'     => '',
					'container_id' => $id_slug,
					'root_id'      => $id_slug,
					'section_id'   => $section_id,
					'group_id'     => '',
					'value'        => null,
					'values'       => $values,
				);
				$this->assert_min_callback_ctx($style_ctx);
				$resolved_style = (string) FormsCallbackInvoker::invoke($section_style_raw, $style_ctx);
				$section_style  = trim($resolved_style);
			} else {
				$section_style = trim((string) $section_style_raw);
			}

			$description_cb  = $meta['description_cb'] ?? null;
			$section_context = array(
				'section_id'  => $section_id,
				'title'       => (string) $meta['title'],
				'description' => is_callable($description_cb) ? (string) ($description_cb)() : (string) ($description_cb ?? ''),
				'inner_html'  => $section_content,
				'before'      => $this->render_callback_output($meta['before'] ?? null, array(
					'field_id'     => '',
					'container_id' => $id_slug,
					'root_id'      => $id_slug,
					'section_id'   => $section_id,
					'group_id'     => '',
					'value'        => null,
					'values'       => $values,
				)) ?? '',
				'after' => $this->render_callback_output($meta['after'] ?? null, array(
					'field_id'     => '',
					'container_id' => $id_slug,
					'root_id'      => $id_slug,
					'section_id'   => $section_id,
					'group_id'     => '',
					'value'        => null,
					'values'       => $values,
				)) ?? '',
				'style'        => $section_style,
				'values'       => $values,
				'root_id'      => $id_slug,
				'container_id' => $id_slug,
			);

			$section_template = '';
			try {
				$section_template = $session->resolve_template('section-wrapper', array_merge($section_context, array(
					'root_id'      => (string) $id_slug,
					'container_id' => (string) $id_slug,
					'section_id'   => (string) $section_id,
					'values'       => $values,
				)));
			} catch (\Throwable $e) {
				$this->logger->warning('FormsCore: Section wrapper template resolution failed, using fallback', array(
					'section_id'        => $section_id,
					'exception_message' => $e->getMessage(),
				));
			}

			if ($section_template === '') {
				$section_template = (string) ($this->get_section_template)();
			}

			$sectionComponent = $this->views->render($section_template, $section_context);

			if (!($sectionComponent instanceof ComponentRenderResult)) {
				throw new \UnexpectedValueException('Section template must return a ComponentRenderResult instance.');
			}
			$session->note_component_used($section_template);

			$all_sections_markup .= $sectionComponent->markup;
		}

		return $all_sections_markup;
	}

	public function render_group_wrapper(array $group, string $fields_content, string $before_content, string $after_content, array $values): string {
		$group_id  = $group['group_id'] ?? '';
		$title     = $group['title']    ?? '';
		$style_raw = $group['style']    ?? '';
		$style     = '';
		if (is_callable($style_raw)) {
			$style_ctx = array(
				'field_id'     => '',
				'container_id' => isset($group['container_id']) ? (string) $group['container_id'] : '',
				'root_id'      => isset($group['root_id']) ? (string) $group['root_id'] : '',
				'section_id'   => isset($group['section_id']) ? (string) $group['section_id'] : '',
				'group_id'     => (string) $group_id,
				'value'        => null,
				'values'       => $values,
			);
			$this->assert_min_callback_ctx($style_ctx);
			$resolved_style = (string) FormsCallbackInvoker::invoke($style_raw, $style_ctx);
			$style          = trim($resolved_style);
		} else {
			$style = trim((string) $style_raw);
		}
		$section_id   = isset($group['section_id']) ? (string) $group['section_id'] : '';
		$container_id = isset($group['container_id']) ? (string) $group['container_id'] : '';
		$root_id      = isset($group['root_id']) ? (string) $group['root_id'] : '';

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
			'section_id'  => $section_id,
		);

		try {
			$element_type = ($group['type'] ?? 'group') === 'fieldset' ? 'fieldset-wrapper' : 'group-wrapper';
			$session      = $this->_ensure_session();
			$result       = $session->render_element($element_type, $group_context, array(
				'group_id'     => (string) $group_id,
				'section_id'   => $section_id,
				'container_id' => $container_id,
				'root_id'      => $root_id,
				'values'       => $values,
			));
			if ($result !== '') {
				return $result;
			}
		} catch (\Throwable $e) {
			$this->logger->warning('FormsCore: Group wrapper template failed, using fallback', array(
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
			$this->logger->warning('FormsCore: Callback provided is not callable', array('context_keys' => array_keys($context)));
			return null;
		}

		$this->assert_min_callback_ctx($context);

		$context_keys = array_keys($context);

		try {
			$result         = (string) FormsCallbackInvoker::invoke($callback, $context);
			$result_length  = strlen($result);
			$preview_length = 120;
			if (ErrorNoticeRenderer::isVerboseDebug()) {
				$this->logger->debug('FormsCore: Callback executed', array(
					'context_keys'     => $context_keys,
					'result_length'    => $result_length,
					'result_preview'   => $preview_length >= $result_length ? $result : substr($result, 0, $preview_length),
					'result_truncated' => $result_length > $preview_length,
				));
			}
			return $result;
		} catch (\Throwable $e) {
			$this->logger->error('FormsCore: Callback execution failed', array(
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

			$field_context['_stored_values'] = $values;
			$field_context['container_id']   = isset($field_item['container_id']) ? (string) $field_item['container_id'] : '';
			$field_context['root_id']        = isset($field_item['root_id']) ? (string) $field_item['root_id'] : '';
			$field_context['section_id']     = isset($field_item['section_id']) ? (string) $field_item['section_id'] : '';
			$field_context['group_id']       = isset($field_item['group_id']) ? (string) $field_item['group_id'] : '';

			$callback_ctx = array(
				'field_id'     => $field_id,
				'container_id' => $field_context['container_id'],
				'root_id'      => $field_context['root_id'],
				'section_id'   => $field_context['section_id'],
				'group_id'     => $field_context['group_id'],
				'value'        => $values[$field_id] ?? null,
				'values'       => $values,
			);
			$this->assert_min_callback_ctx($callback_ctx);

			$bool_keys = array('disabled', 'readonly', 'required');
			foreach ($bool_keys as $key) {
				if (!isset($field_context[$key]) || !is_callable($field_context[$key])) {
					continue;
				}

				$resolved = FormsCallbackInvoker::invoke($field_context[$key], $callback_ctx);
				if ($resolved) {
					$field_context[$key] = true;
				} else {
					unset($field_context[$key]);
				}
			}

			$value_keys = array('default', 'options');
			foreach ($value_keys as $key) {
				if (!isset($field_context[$key]) || !is_callable($field_context[$key])) {
					continue;
				}

				$resolved = FormsCallbackInvoker::invoke($field_context[$key], $callback_ctx);
				if ($resolved === null || $resolved === '' || $resolved === array()) {
					unset($field_context[$key]);
				} else {
					$field_context[$key] = $resolved;
				}
			}

			if (isset($field_context['style'])) {
				if (is_callable($field_context['style'])) {
					$resolved_style         = FormsCallbackInvoker::invoke($field_context['style'], $callback_ctx);
					$field_context['style'] = trim((string) $resolved_style);
				} else {
					$field_context['style'] = trim((string) $field_context['style']);
				}
				if ($field_context['style'] === '') {
					unset($field_context['style']);
				}
			}

			if (isset($field_context['options']) && is_array($field_context['options'])) {
				foreach ($field_context['options'] as $idx => $option) {
					if (!is_array($option)) {
						continue;
					}

					if (isset($option['disabled']) && is_callable($option['disabled'])) {
						$resolved = FormsCallbackInvoker::invoke($option['disabled'], $callback_ctx);
						if ($resolved) {
							$option['disabled'] = true;
							if (!isset($option['attributes']) || !is_array($option['attributes'])) {
								$option['attributes'] = array();
							}
							$option['attributes']['disabled'] = 'disabled';
						} else {
							unset($option['disabled']);
							if (isset($option['attributes']) && is_array($option['attributes'])) {
								unset($option['attributes']['disabled']);
							}
						}
					}

					$field_context['options'][$idx] = $option;
				}
			}

			if ($component === 'radio-group' && isset($field_context['default']) && is_string($field_context['default']) && isset($field_context['options']) && is_array($field_context['options'])) {
				foreach ($field_context['options'] as $idx => $option) {
					if (!is_array($option) || !isset($option['value'])) {
						continue;
					}
					if ((string) $option['value'] === $field_context['default']) {
						$option['checked']              = true;
						$field_context['options'][$idx] = $option;
					}
				}
			}

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
			$this->assert_min_callback_ctx($context);
			return (string) FormsCallbackInvoker::invoke($content, $context);
		}

		return (string) $content;
	}

	public function render_hr_content(array $field, array $context): string {
		$component_context = $field['component_context'] ?? array();
		$style_raw         = $component_context['style'] ?? '';
		$style_classes     = '';
		if (is_callable($style_raw)) {
			$this->assert_min_callback_ctx($context);
			$resolved_style = (string) FormsCallbackInvoker::invoke($style_raw, $context);
			$style_classes  = trim($resolved_style);
		} else {
			$style_classes = trim((string) $style_raw);
		}

		$this->assert_min_callback_ctx($context);

		$before = '';
		if (isset($field['before']) && is_callable($field['before'])) {
			$before = (string) FormsCallbackInvoker::invoke($field['before'], $context);
		}

		$after = '';
		if (isset($field['after']) && is_callable($field['after'])) {
			$after = (string) FormsCallbackInvoker::invoke($field['after'], $context);
		}

		$class_attr = 'kplr-hr' . ($style_classes !== '' ? ' ' . $style_classes : '');

		$hr = '<hr class="' . esc_attr($class_attr) . '">';

		return $before . $hr . $after;
	}

	private function assert_min_callback_ctx(array $context): void {
		$required_keys = array('field_id', 'container_id', 'root_id', 'section_id', 'group_id', 'value', 'values');
		$missing       = array();
		foreach ($required_keys as $key) {
			if (!array_key_exists($key, $context)) {
				$missing[] = $key;
			}
		}

		if ($missing !== array()) {
			throw new \InvalidArgumentException('FormsCore: Callback context missing required keys: ' . implode(', ', $missing));
		}

		if (!is_array($context['values'])) {
			throw new \InvalidArgumentException('FormsCore: Callback context "values" must be an array.');
		}
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
