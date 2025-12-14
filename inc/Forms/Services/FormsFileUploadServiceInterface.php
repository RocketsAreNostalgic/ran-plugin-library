<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Services;

interface FormsFileUploadServiceInterface {
	/**
	 * @param array<string,mixed> $files
	 *
	 * @return array<string, array<string,mixed>>
	 */
	public function process_uploaded_files(array $files): array;

	/**
	 * @param array<string,mixed> $file
	 *
	 * @return array<string,mixed>|null
	 */
	public function process_single_file_upload(array $file): ?array;

	/**
	 * @param array<string,mixed> $upload_result
	 */
	public function create_media_attachment(array $upload_result): ?int;
}
