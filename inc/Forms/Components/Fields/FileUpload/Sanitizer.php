<?php
/**
 * FileUpload component sanitizer.
 *
 * Handles actual file uploads via WordPress APIs and stores attachment IDs or URLs.
 * Sanitizes file upload input by processing $_FILES, uploading to media library,
 * and filtering to allowed extensions.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\FileUpload;

use Ran\PluginLib\Util\Validate;
use Ran\PluginLib\Util\WPWrappersTrait;
use Ran\PluginLib\Forms\Validation\Helpers;
use Ran\PluginLib\Forms\Component\Sanitize\SanitizerBase;

final class Sanitizer extends SanitizerBase {
	use WPWrappersTrait;

	/**
	 * Sanitize file upload value.
	 *
	 * Handles actual file uploads from $_FILES, processes them via wp_handle_upload(),
	 * and optionally creates media library attachments.
	 *
	 * The stored value is an array with:
	 * - url: The file URL
	 * - file: The server file path
	 * - type: The MIME type
	 * - attachment_id: (optional) The media library attachment ID
	 *
	 * For multiple files, returns an array of such arrays.
	 *
	 * @param mixed               $value      The submitted value (filename from browser).
	 * @param array<string,mixed> $context    The field context.
	 * @param callable            $emitNotice Callback to emit sanitization notices.
	 *
	 * @return array<string,mixed>|array<int,array<string,mixed>>|string The uploaded file data or empty.
	 */
	protected function _sanitize_component(mixed $value, array $context, callable $emitNotice): mixed {
		$multiple  = Helpers::sanitizeBoolean($context['multiple'] ?? false, 'multiple_upload', $this->logger);
		$fieldName = $context['name'] ?? '';

		// Check if there's an actual file upload in $_FILES
		$uploadedFile = $this->_get_uploaded_file($fieldName);

		// No new file uploaded - preserve existing value if it's already processed
		if ($uploadedFile === null) {
			// If value is already a processed file array, keep it
			if (is_array($value) && isset($value['url'])) {
				return $value;
			}
			// If value is an array of processed files, keep it
			if (is_array($value) && !empty($value) && isset($value[0]['url'])) {
				return $value;
			}
			// Empty or just filename string with no upload - return empty
			return $multiple ? array() : '';
		}

		// Get allowed extensions from context
		$allowedExtensions = $this->_get_allowed_extensions($context);

		// Process the uploaded file
		$result = $this->_process_upload($uploadedFile, $allowedExtensions, $emitNotice, $context);

		if ($result === null) {
			// Upload failed, preserve previous value if valid
			if (is_array($value) && isset($value['url'])) {
				return $value;
			}
			return $multiple ? array() : '';
		}

		return $multiple ? array($result) : $result;
	}

	/**
	 * Get uploaded file data from $_FILES.
	 *
	 * Handles both simple names (e.g., 'my_file') and WordPress Settings API
	 * array-style names (e.g., 'option_name[field_id]').
	 *
	 * @param string $fieldName The field name to look for.
	 *
	 * @return array<string,mixed>|null The file data or null if not found.
	 */
	private function _get_uploaded_file(string $fieldName): ?array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$files = $_FILES;

		// Check for array-style name like "option_name[field_id]"
		if (preg_match('/^([^\[]+)\[([^\]]+)\]$/', $fieldName, $matches)) {
			$optionName = $matches[1];
			$fieldKey   = $matches[2];

			// WordPress restructures array-style file inputs
			if (!isset($files[$optionName]) || !is_array($files[$optionName])) {
				return null;
			}

			$optionFiles = $files[$optionName];

			// Check if the specific field exists in the nested structure
			if (!isset($optionFiles['name'][$fieldKey])) {
				return null;
			}

			// Reconstruct the file array for this specific field
			$file = array(
				'name'     => $optionFiles['name'][$fieldKey]     ?? '',
				'type'     => $optionFiles['type'][$fieldKey]     ?? '',
				'tmp_name' => $optionFiles['tmp_name'][$fieldKey] ?? '',
				'error'    => $optionFiles['error'][$fieldKey]    ?? UPLOAD_ERR_NO_FILE,
				'size'     => $optionFiles['size'][$fieldKey]     ?? 0,
			);
		} else {
			// Simple field name
			if (!isset($files[$fieldName])) {
				return null;
			}
			$file = $files[$fieldName];
		}

		// Check if a file was actually uploaded
		if (!is_array($file) || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
			return null;
		}

		// Check for upload errors
		if ($file['error'] !== UPLOAD_ERR_OK) {
			return null;
		}

		// Verify the file exists
		if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
			return null;
		}

		return $file;
	}

	/**
	 * Process a single file upload.
	 *
	 * @param array<string,mixed> $file               The $_FILES entry.
	 * @param array<string>       $allowedExtensions  Allowed file extensions.
	 * @param callable            $emitNotice         Callback to emit notices.
	 * @param array<string,mixed> $context            The field context.
	 *
	 * @return array<string,mixed>|null The processed file data or null on failure.
	 */
	private function _process_upload(array $file, array $allowedExtensions, callable $emitNotice, array $context): ?array {
		// Validate extension before upload
		$filename  = sanitize_file_name($file['name'] ?? '');
		$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

		if (!empty($allowedExtensions) && !in_array($extension, $allowedExtensions, true)) {
			$emitNotice($this->_translate('File type not allowed.'));
			$this->logger->warning('FileUpload Sanitizer: Rejected file with disallowed extension', array(
				'filename'  => $filename,
				'extension' => $extension,
				'allowed'   => $allowedExtensions,
			));
			return null;
		}

		// Use WordPress upload handler
		if (!function_exists('wp_handle_upload')) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$overrides = array(
			'test_form' => false,
			'test_type' => true,
		);

		$upload = $this->_do_wp_handle_upload($file, $overrides);

		if (!is_array($upload) || isset($upload['error'])) {
			$errorMsg = $upload['error'] ?? $this->_translate('Upload failed.');
			$emitNotice($errorMsg);
			$this->logger->error('FileUpload Sanitizer: wp_handle_upload failed', array(
				'filename' => $filename,
				'error'    => $errorMsg,
			));
			return null;
		}

		$result = array(
			'url'      => $upload['url']  ?? '',
			'file'     => $upload['file'] ?? '',
			'type'     => $upload['type'] ?? '',
			'filename' => $filename,
		);

		// Optionally create media library attachment
		$createAttachment = $context['create_attachment'] ?? true;
		if ($createAttachment && !empty($result['file'])) {
			$attachmentId = $this->_create_attachment($result, $context);
			if ($attachmentId > 0) {
				$result['attachment_id'] = $attachmentId;
			}
		}

		$this->logger->debug('FileUpload Sanitizer: File uploaded successfully', array(
			'filename'      => $filename,
			'url'           => $result['url'],
			'attachment_id' => $result['attachment_id'] ?? null,
		));

		return $result;
	}

	/**
	 * Create a media library attachment for the uploaded file.
	 *
	 * @param array<string,mixed> $fileData The uploaded file data.
	 * @param array<string,mixed> $context  The field context.
	 *
	 * @return int The attachment ID or 0 on failure.
	 */
	private function _create_attachment(array $fileData, array $context): int {
		if (!function_exists('wp_insert_attachment')) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		$filePath = $fileData['file']     ?? '';
		$fileUrl  = $fileData['url']      ?? '';
		$fileType = $fileData['type']     ?? '';
		$filename = $fileData['filename'] ?? basename($filePath);

		if (empty($filePath) || !file_exists($filePath)) {
			return 0;
		}

		$attachment = array(
			'guid'           => $fileUrl,
			'post_mime_type' => $fileType,
			'post_title'     => preg_replace('/\.[^.]+$/', '', $filename),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachmentId = $this->_do_wp_insert_attachment($attachment, $filePath, 0);

		if (is_wp_error($attachmentId) || $attachmentId === 0) {
			$this->logger->warning('FileUpload Sanitizer: Failed to create attachment', array(
				'filename' => $filename,
				'error'    => is_wp_error($attachmentId) ? $attachmentId->get_error_message() : 'Unknown error',
			));
			return 0;
		}

		// Generate attachment metadata
		if (function_exists('wp_generate_attachment_metadata')) {
			$metadata = wp_generate_attachment_metadata($attachmentId, $filePath);
			wp_update_attachment_metadata($attachmentId, $metadata);
		}

		return (int) $attachmentId;
	}

	/**
	 * Wrapper for wp_handle_upload (for testing).
	 *
	 * @param array<string,mixed> $file      The $_FILES entry.
	 * @param array<string,mixed> $overrides Upload overrides.
	 *
	 * @return array<string,mixed> The upload result.
	 */
	protected function _do_wp_handle_upload(array $file, array $overrides): array {
		return wp_handle_upload($file, $overrides);
	}

	/**
	 * Wrapper for wp_insert_attachment (for testing).
	 *
	 * @param array<string,mixed> $attachment Attachment data.
	 * @param string              $filePath   File path.
	 * @param int                 $parentId   Parent post ID.
	 *
	 * @return int|\WP_Error The attachment ID or error.
	 */
	protected function _do_wp_insert_attachment(array $attachment, string $filePath, int $parentId): int|\WP_Error {
		return wp_insert_attachment($attachment, $filePath, $parentId);
	}

	/**
	 * Get allowed extensions from context.
	 *
	 * @param array<string,mixed> $context The field context.
	 *
	 * @return array<string> Normalized array of allowed extensions (lowercase).
	 */
	private function _get_allowed_extensions(array $context): array {
		if (!isset($context['allowed_extensions']) || !is_array($context['allowed_extensions'])) {
			return array();
		}

		// Normalize to lowercase
		return array_map('strtolower', array_map('trim', $context['allowed_extensions']));
	}
}
