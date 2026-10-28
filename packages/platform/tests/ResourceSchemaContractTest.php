<?php

namespace Nreljed\Platform\Tests;

use Nreljed\Platform\Support\ResourceDefinition;
use PHPUnit\Framework\TestCase;

/**
 * The kernel ↔ shipped-schema lock-step guarantee.
 *
 * `ResourceDefinition::FIELD_TYPES` and `ROUTE_KEYS` are the server-side half
 * of the contracts in `resources/js/lib/resource-schema.ts` and the built-in
 * registry in `Components/resources/fields.tsx`. If the TypeScript side adds
 * a field type or a route slot and nobody updates the PHP constant, a module
 * can legitimately declare something the kernel lets through but the runtime
 * cannot render — the exact drift the plan calls "a module silently forking
 * the shared admin surface".
 *
 * These tests read the shipped TypeScript source, so the list cannot rot the
 * way a hand-copied mirror would.
 */
final class ResourceSchemaContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__).'/resources/js';
    }

    public function test_field_types_match_the_shipped_typescript_union_minus_dynamic(): void
    {
        $source = $this->read('lib/resource-schema.ts');

        preg_match('/export type FieldType\s*=\s*(.*?);/s', $source, $matches);

        $this->assertArrayHasKey(1, $matches, 'resource-schema.ts must declare the FieldType union.');

        /* CamelCase members (`checkboxList`) must be captured too - a
         * lowercase-only class would quietly drop them from the comparison and
         * let the two halves drift apart. */
        preg_match_all('/\|\s*"([a-zA-Z]+)"/', $matches[1], $typeMatches);

        $tsFieldTypes = $typeMatches[1] ?? [];

        $this->assertContains('text', $tsFieldTypes, 'Sanity: the union must at least contain "text".');

        sort($tsFieldTypes);

        // `dynamic` exists to carry a render closure; a PHP descriptor cannot
        // express a closure, so the kernel deliberately rejects it as a type a
        // descriptor may declare.
        $expected = array_values(array_diff($tsFieldTypes, ['dynamic']));

        sort($expected);

        $phpFieldTypes = ResourceDefinition::FIELD_TYPES;
        sort($phpFieldTypes);

        $this->assertSame(
            $expected,
            $phpFieldTypes,
            'ResourceDefinition::FIELD_TYPES drifted from the FieldType union in resource-schema.ts.',
        );
    }

    public function test_built_in_field_controls_cover_every_php_field_type(): void
    {
        $source = $this->read('Components/resources/fields.tsx');

        preg_match('/const BUILT_INS: Record<.*?>(.*?)};/s', $source, $matches);

        $this->assertArrayHasKey(1, $matches, 'fields.tsx must declare the BUILT_INS registry.');

        /* `[a-z]+` cannot span the camelCase `checkboxList` the registry
         * declares, so the type name is matched as a word instead - otherwise
         * this check silently passes on any camelCase type and drifts. */
        preg_match_all('/^\s{4}([a-zA-Z]+):\s*\{/m', $matches[1], $registered);

        $registeredTypes = $registered[1] ?? [];

        $this->assertSame(
            [],
            array_values(array_diff(ResourceDefinition::FIELD_TYPES, $registeredTypes)),
            'A ResourceDefinition::FIELD_TYPES entry has no built-in control registered in fields.tsx.',
        );

        $this->assertSame(
            [],
            array_values(array_diff($registeredTypes, ResourceDefinition::FIELD_TYPES)),
            'fields.tsx registers a type the PHP descriptor contract does not know about.',
        );
    }

    public function test_route_slots_match_the_typescript_routes_shape(): void
    {
        $source = $this->read('lib/resource-schema.ts');

        preg_match('/routes\?\s*:\s*\{(.*?)\n\s*\};/s', $source, $matches);

        $this->assertArrayHasKey(1, $matches, 'resource-schema.ts must declare the ResourceDefinition routes shape.');

        preg_match_all('/^\s*([a-z]+)\??:\s/m', $matches[1], $slotMatches);

        $tsSlots = array_values(array_unique($slotMatches[1] ?? []));

        sort($tsSlots);

        $expected = ResourceDefinition::ROUTE_KEYS;
        sort($expected);

        $this->assertSame(
            $expected,
            $tsSlots,
            'ResourceDefinition::ROUTE_KEYS drifted from the routes shape in resource-schema.ts.',
        );
    }

    private function read(string $relative): string
    {
        $content = file_get_contents($this->root.'/'.$relative);

        $this->assertNotFalse($content, "Expected a TypeScript source at {$this->root}/{$relative}.");

        return (string) $content;
    }
}
