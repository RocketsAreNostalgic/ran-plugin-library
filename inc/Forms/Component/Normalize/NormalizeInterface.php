<?php
/**
 * Normalizer interface.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Component\Normalize;

use Ran\PluginLib\Util\TranslationService;
use Ran\PluginLib\Forms\Component\Normalize\ComponentNormalizationContext as NormalizationContext;
use Ran\PluginLib\Forms\Component\ComponentLoader;

interface NormalizeInterface {
	public function __construct(ComponentLoader $views);

	/**
	 * Renders the component.
	 *
	 * @param array<string,mixed> $context Raw component data keyed by the template’s context schema.
	 * @return array{
	 *     payload: array{
	 *         markup: string,
	 *         script: mixed,
	 *         style: mixed,
	 *         requires_media: bool,
	 *         context_schema: array{required: array<int,string>, optional: array<int,string>, defaults: array<string,mixed>}
	 *     },
	 *     warnings: array<int,string>
	 * }
	 */
	public function render(array $context, NormalizationContext $session, string $componentAlias): array;

	/**
	 * Create a translation service for normalizers.
	 *
	 * @param string $textDomain The WordPress text domain to use.
	 *
	 * @return TranslationService
	 */
	public static function create_translation_service(string $textDomain = 'ran-plugin-lib'): TranslationService;
}
