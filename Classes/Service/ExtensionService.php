<?php

declare(strict_types=1);

namespace Pint\LimitToPages\Service;

use TYPO3\CMS\Core\SingletonInterface;

use function str_replace;
use function strtolower;
use function ucwords;

class ExtensionService implements SingletonInterface
{
    public function getPluginSignature(string $extensionName, string $pluginName): string
    {
        if ($extensionName === '' || $pluginName === '') {
            return '';
        }

        return strtolower(str_replace(' ', '', ucwords(str_replace('_', ' ', $extensionName))) . '_' . $pluginName);
    }
}
