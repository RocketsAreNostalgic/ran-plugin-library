<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Services;

use Ran\PluginLib\Util\Logger;

class FormsFileUploadService implements FormsFileUploadServiceInterface {
	/** @var callable(string):bool */
	private $is_uploaded_file;

	/** @var callable(array,mixed=,string=):array */
	private $wp_handle_upload;

	/** @var callable(string):string */
	private $sanitize_file_name;

	/** @var callable(array,string,int,bool):mixed */
	private $wp_insert_attachment;

	/** @var callable(mixed):bool */
	private $is_wp_error;

	/** @var callable(int,string):array */
	private $wp_generate_attachment_metadata;

	/** @var callable(int,array):mixed */
	private $wp_update_attachment_metadata;

	/** @var callable():void */
	private $load_image_library;

	/**
	 * @param callable(string):bool $is_uploaded_file
	 * @param callable(array,mixed=,string=):array $wp_handle_upload
	 * @param callable(string):string $sanitize_file_name
	 * @param callable(array,string,int,bool):mixed $wp_insert_attachment
	 * @param callable(mixed):bool $is_wp_error
	 * @param callable(int,string):array $wp_generate_attachment_metadata
	 * @param callable(int,array):mixed $wp_update_attachment_metadata
	 * @param callable():void $load_image_library
	 */
	public function __construct(
		private Logger $logger,
		private string $main_option,
		callable $is_uploaded_file,
		callable $wp_handle_upload,
		callable $sanitize_file_name,
		callable $wp_insert_attachment,
		callable $is_wp_error,
		callable $wp_generate_attachment_metadata,
		callable $wp_update_attachment_metadata,
		callable $load_image_library
	) {
		$this->is_uploaded_file                = $is_uploaded_file;
		$this->wp_handle_upload                = $wp_handle_upload;
		$this->sanitize_file_name              = $sanitize_file_name;
		$this->wp_insert_attachment            = $wp_insert_attachment;
		$this->is_wp_error                     = $is_wp_error;
		$this->wp_generate_attachment_metadata = $wp_generate_attachment_metadata;
		$this->wp_update_attachment_metadata   = $wp_update_attachment_metadata;
		$this->load_image_library              = $load_image_library;
	}

	public function process_uploaded_files(array $files): array {
		$processed = array();

		if (!isset($files[$this->main_option]) || !\is_array($files[$this->main_option])) {
			return $processed;
		}

		$optionFiles = $files[$this->main_option];

		foreach ($optionFiles['name'] as $fieldKey => $fileName) {
			if (empty($fileName) || $optionFiles['error'][$fieldKey] === \UPLOAD_ERR_NO_FILE) {
				continue;
			}

			if ($optionFiles['error'][$fieldKey] !== \UPLOAD_ERR_OK) {
				$this->logger->warning('FormsFileUploadService.process_uploaded_files: Upload error', array(
					'field'      => $fieldKey,
					'error_code' => $optionFiles['error'][$fieldKey],
				));
				continue;
			}

			$file = array(
				'name'     => $optionFiles['name'][$fieldKey],
				'type'     => $optionFiles['type'][$fieldKey],
				'tmp_name' => $optionFiles['tmp_name'][$fieldKey],
				'error'    => $optionFiles['error'][$fieldKey],
				'size'     => $optionFiles['size'][$fieldKey],
			);

			$result = $this->process_single_file_upload($file);

			if ($result !== null) {
				$processed[$fieldKey] = $result;
				$this->logger->debug('FormsFileUploadService.process_uploaded_files: File processed', array(
					'field'  => $fieldKey,
					'result' => $result,
				));
			}
		}

		return $processed;
	}

	public function process_single_file_upload(array $file): ?array {
		if (empty($file['tmp_name']) || !\call_user_func($this->is_uploaded_file, $file['tmp_name'])) {
			return null;
		}

		$overrides = array(
			'test_form' => false,
			'test_type' => true,
		);

		$result = (array) \call_user_func($this->wp_handle_upload, $file, $overrides);

		if (isset($result['error'])) {
			$this->logger->warning('FormsFileUploadService.process_single_file_upload: Upload failed', array(
				'error' => $result['error'],
				'file'  => $file['name'],
			));
			return null;
		}

		$fileData = array(
			'url'      => $result['url'],
			'file'     => $result['file'],
			'type'     => $result['type'],
			'filename' => \call_user_func($this->sanitize_file_name, (string) $file['name']),
		);

		$attachmentId = $this->create_media_attachment($result);
		if ($attachmentId !== null) {
			$fileData['attachment_id'] = $attachmentId;
		}

		return $fileData;
	}

	public function create_media_attachment(array $upload_result): ?int {
		$filePath = $upload_result['file'];
		$fileUrl  = $upload_result['url'];
		$fileType = $upload_result['type'];

		$attachment = array(
			'guid'           => $fileUrl,
			'post_mime_type' => $fileType,
			'post_title'     => \preg_replace('/\.[^.]+$/', '', \basename($filePath)),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachmentId = \call_user_func($this->wp_insert_attachment, $attachment, $filePath);

		if (\call_user_func($this->is_wp_error, $attachmentId)) {
			$this->logger->warning('FormsFileUploadService.create_media_attachment: Failed to create attachment', array(
				'error' => $attachmentId->get_error_message(),
			));
			return null;
		}

		\call_user_func($this->load_image_library);
		$attachmentData = (array) \call_user_func($this->wp_generate_attachment_metadata, (int) $attachmentId, $filePath);
		\call_user_func($this->wp_update_attachment_metadata, (int) $attachmentId, $attachmentData);

		return (int) $attachmentId;
	}
}
