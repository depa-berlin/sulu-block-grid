<?php

declare(strict_types=1);

namespace Depa\SuluBlockGridBundle\DependencyInjection;

use Symfony\Component\Finder\Finder;

trait BlockMetadataLoaderTrait
{
    /**
     * @return array{blocks: list<string>, children: array<string, list<string>>}
     */
    private function loadMetadataFromXml(string $blocksDir): array
    {
        if (!is_dir($blocksDir)) {
            return ['blocks' => [], 'children' => []];
        }

        $blocks = [];
        $children = [];

        $finder = (new Finder())->files()->in($blocksDir)->name('*.xml');

        foreach ($finder as $file) {
            $xml = @simplexml_load_file($file->getRealPath());
            if ($xml === false) {
                continue;
            }

            $xml->registerXPathNamespace('s', 'http://schemas.sulu.io/template/template');

            $keyNodes = $xml->xpath('//s:key');
            if (empty($keyNodes)) {
                continue;
            }

            $blockName = (string) $keyNodes[0];
            $blocks[] = $blockName;

            $refs = $xml->xpath('//s:block//s:type/@ref');
            if (!empty($refs)) {
                $mapped = array_map(static fn($r) => (string) $r, $refs);
                $children[$blockName] = array_values(array_unique($mapped));
            }
        }

        sort($blocks);

        return ['blocks' => $blocks, 'children' => $children];
    }
}
