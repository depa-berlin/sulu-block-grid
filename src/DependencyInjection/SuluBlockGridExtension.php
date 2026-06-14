<?php

declare(strict_types=1);

namespace Depa\SuluBlockGridBundle\DependencyInjection;

use Depa\SuluBlockHelperBundle\DependencyInjection\AbstractBlockExtension;

class SuluBlockGridExtension extends AbstractBlockExtension
{
    protected function getBundleName(): string
    {
        return 'SuluBlockGridBundle';
    }

    protected function getPackageName(): string
    {
        return 'depa-berlin/sulu-block-grid';
    }

    protected function getMetadataParameterName(): string
    {
        return 'sulu_block_grid.bundle_metadata';
    }

    protected function getSuluAdminTemplateKey(): string
    {
        return 'sulu_block_grid';
    }
}
