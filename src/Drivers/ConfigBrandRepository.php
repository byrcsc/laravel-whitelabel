<?php

declare(strict_types=1);

namespace Byrcsc\Whitelabel\Drivers;

use Byrcsc\Whitelabel\Brand;
use Byrcsc\Whitelabel\BrandDefinition;
use Byrcsc\Whitelabel\Contracts\BrandRepository;
use Byrcsc\Whitelabel\Exceptions\InvalidBrandDefinition;
use Byrcsc\Whitelabel\Exceptions\UnknownBrand;
use Byrcsc\Whitelabel\Exceptions\UnsupportedBrandOperation;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Reads brands from `config/whitelabel.php`.
 *
 * The driver is read-only: brands live in a file the application owns, so the
 * write methods throw rather than pretending to persist anything. Hydration
 * happens once and is redone whenever the underlying config changes, which is
 * what makes rewriting `whitelabel.brands` inside a test take effect.
 */
final class ConfigBrandRepository implements BrandRepository
{
    public const NAME = 'config';

    /**
     * @var array<string, Brand>|null
     */
    private ?array $brands = null;

    /**
     * The config this driver last hydrated from.
     *
     * @var array{default: mixed, brands: mixed}|null
     */
    private ?array $source = null;

    public function __construct(private readonly Config $config) {}

    public function all(): array
    {
        $source = $this->source();

        if ($this->brands === null || $this->source !== $source) {
            $this->brands = $this->hydrate($source);
            $this->source = $source;
        }

        return $this->brands;
    }

    public function find(string $id): ?Brand
    {
        return $this->all()[$id] ?? null;
    }

    public function findByDomain(string $domain): ?Brand
    {
        $domain = mb_strtolower($domain);

        foreach ($this->all() as $brand) {
            if ($brand->domain() === $domain) {
                return $brand;
            }
        }

        return null;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->all());
    }

    public function create(string $id, array $definition): Brand
    {
        throw UnsupportedBrandOperation::write(self::NAME, 'create');
    }

    public function update(string $id, array $definition): Brand
    {
        throw UnsupportedBrandOperation::write(self::NAME, 'update');
    }

    public function delete(string $id): bool
    {
        throw UnsupportedBrandOperation::write(self::NAME, 'delete');
    }

    public function flush(): void
    {
        $this->brands = null;
        $this->source = null;
    }

    /**
     * @return array{default: mixed, brands: mixed}
     */
    private function source(): array
    {
        return [
            'default' => $this->config->get('whitelabel.default'),
            'brands' => $this->config->get('whitelabel.brands', []),
        ];
    }

    /**
     * @param  array{default: mixed, brands: mixed}  $source
     * @return array<string, Brand>
     */
    private function hydrate(array $source): array
    {
        $definitions = is_array($source['brands']) ? $source['brands'] : [];

        $defaultId = is_string($source['default']) && $source['default'] !== ''
            ? $source['default']
            : 'default';

        // Validate everything first, so a broken definition is reported ahead
        // of the vaguer complaint that the default brand is missing.
        $validated = [];

        foreach ($definitions as $id => $definition) {
            $id = (string) $id;

            if (! is_array($definition)) {
                throw InvalidBrandDefinition::at(
                    $id,
                    'brand',
                    'must be an array of brand keys, '.get_debug_type($definition).' given',
                );
            }

            $validated[$id] = BrandDefinition::validate($id, $definition);
        }

        if ($validated === []) {
            return [];
        }

        if (! array_key_exists($defaultId, $validated)) {
            throw UnknownBrand::defaultBrand($defaultId, array_keys($validated));
        }

        $default = new Brand($defaultId, $this->applyPackageDefaults($validated[$defaultId]));

        $brands = [];

        foreach ($validated as $id => $definition) {
            $brands[$id] = $id === $defaultId ? $default : new Brand($id, $definition, $default);
        }

        return $brands;
    }

    /**
     * Fill in what the package can supply for the default brand.
     *
     * The shipped config leaves `name` out so it tracks the application name
     * without an `env()` call in a package config file.
     *
     * @param  array<array-key, mixed>  $definition
     * @return array<array-key, mixed>
     */
    private function applyPackageDefaults(array $definition): array
    {
        if (array_key_exists(BrandDefinition::NAME, $definition)) {
            return $definition;
        }

        $appName = $this->config->get('app.name');

        if (is_string($appName) && $appName !== '') {
            $definition[BrandDefinition::NAME] = $appName;
        }

        return $definition;
    }
}
