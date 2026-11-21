<?php

use CraftCms\Cms\Gql\Data\GqlSchema;
use CraftCms\Cms\Gql\Gql;
use CraftCms\Cms\Support\Facades\Gql as GqlFacade;

/**
 * Runs a query against a schema with the given scope, as Commerce's own GraphQL tests do.
 *
 * @param  string[]  $scope
 */
function digitalProductsGql(array $scope, string $query): array
{
    config()->set('app.debug', true);
    app(Gql::class)->flushCaches();

    $schema = new GqlSchema(['name' => 'Test '.bin2hex(random_bytes(4)), 'scope' => $scope]);
    GqlFacade::setActiveSchema($schema);

    return GqlFacade::executeQuery($schema, $query, debugMode: true);
}

it('queries digital products of the product types in the schema', function () {
    $software = createProductType('software');
    $ebooks = createProductType('ebooks');
    createProduct($software, ['title' => 'Plugin', 'sku' => 'PLUGIN', 'price' => 99]);
    createProduct($ebooks, ['title' => 'Book', 'sku' => 'BOOK']);

    $result = digitalProductsGql(
        ["digitalProductTypes.$software->uid:read"],
        '{ digitalProducts { title sku price productTypeHandle } digitalProduct(sku: "PLUGIN") { title } }',
    );

    expect($result['errors'] ?? null)->toBeNull(json_encode($result['errors'] ?? null))
        ->and($result['data']['digitalProducts'])->toBe([
            ['title' => 'Plugin', 'sku' => 'PLUGIN', 'price' => 99.0, 'productTypeHandle' => 'software'],
        ])
        ->and($result['data']['digitalProduct'])->toBe(['title' => 'Plugin']);
});

it('does not expose digital products to a schema without access', function () {
    createProduct(createProductType());

    $result = digitalProductsGql(['sites.*:read'], '{ digitalProducts { title } }');

    expect($result['errors'][0]['message'] ?? '')->toContain('digitalProducts');
});

it('lists a schema permission per product type', function () {
    $productType = createProductType();

    $components = app(Gql::class)->getAllSchemaComponents();

    expect(json_encode($components))->toContain("digitalProductTypes.$productType->uid:read");
});
