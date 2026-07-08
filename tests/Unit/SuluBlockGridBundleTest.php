<?php

declare(strict_types=1);

namespace Depa\SuluBlockGridBundle\Tests\Unit;

use Depa\SuluBlockGridBundle\SuluBlockGridBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SuluBlockGridBundleTest extends TestCase
{
    private ContainerBuilder $container;
    private SuluBlockGridBundle $bundle;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->bundle = new SuluBlockGridBundle();
    }

    private function load(): void
    {
        $this->bundle->getContainerExtension()->load([], $this->container);
    }

    public function testLoadSetsBundleMetadataParameter(): void
    {
        $this->load();
        self::assertTrue($this->container->hasParameter('sulu_block_grid.bundle_metadata'));
    }

    public function testBundleMetadataHasRequiredKeys(): void
    {
        $this->load();
        $meta = $this->container->getParameter('sulu_block_grid.bundle_metadata');
        self::assertIsArray($meta);
        self::assertArrayHasKey('bundle', $meta);
        self::assertArrayHasKey('package', $meta);
        self::assertArrayHasKey('blocks', $meta);
        self::assertArrayHasKey('children', $meta);
    }

    public function testBundleMetadataContainsCorrectBundleName(): void
    {
        $this->load();
        $meta = $this->container->getParameter('sulu_block_grid.bundle_metadata');
        self::assertIsArray($meta);
        self::assertSame('SuluBlockGridBundle', $meta['bundle']);
    }

    public function testBundleMetadataContainsCorrectPackageName(): void
    {
        $this->load();
        $meta = $this->container->getParameter('sulu_block_grid.bundle_metadata');
        self::assertIsArray($meta);
        self::assertSame('depa/sulu-block-grid', $meta['package']);
    }

    public function testBundleMetadataContainsAtLeastOneBlock(): void
    {
        $this->load();
        $meta = $this->container->getParameter('sulu_block_grid.bundle_metadata');
        self::assertIsArray($meta);
        self::assertNotEmpty($meta['blocks']);
    }

    public function testBlocksAreSortedAndUnique(): void
    {
        $this->load();
        $meta = $this->container->getParameter('sulu_block_grid.bundle_metadata');
        self::assertIsArray($meta);
        $blocks = $meta['blocks'];
        $sorted = $blocks;
        sort($sorted);
        self::assertSame($sorted, $blocks, 'blocks must be sorted');
        self::assertSame(array_unique($blocks), $blocks, 'blocks must be unique');
    }

    public function testKnownGridBlocksArePresent(): void
    {
        $this->load();
        $meta = $this->container->getParameter('sulu_block_grid.bundle_metadata');
        self::assertIsArray($meta);

        foreach (['block--css-grid', 'block--grid-row', 'block--grid-col', 'block--grid-three-col'] as $expected) {
            self::assertContains($expected, $meta['blocks']);
        }
    }

    public function testChildrenValuesAreArraysOfStrings(): void
    {
        $this->load();
        $meta = $this->container->getParameter('sulu_block_grid.bundle_metadata');
        self::assertIsArray($meta);

        foreach ($meta['children'] as $parent => $kids) {
            self::assertIsArray($kids, "Children of '{$parent}' must be an array");
            foreach ($kids as $child) {
                self::assertIsString($child);
            }
        }
    }

    public function testGridRowHasChildrenFromXml(): void
    {
        $this->load();
        $meta = $this->container->getParameter('sulu_block_grid.bundle_metadata');
        self::assertIsArray($meta);

        self::assertArrayHasKey('block--grid-row', $meta['children']);
        self::assertContains('block--grid-col', $meta['children']['block--grid-row']);
    }
}
