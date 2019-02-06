<?php

namespace webignition\ResourceStorage;

use webignition\UrlSourceMap\SourceMap;

class SourcePurger
{
    public function purgeLocalResources(SourceMap $sources)
    {
        $filePathPattern = '/^file:/';

        foreach ($sources as $source) {
            $mappedUri = $source->getMappedUri();

            if (is_string($mappedUri) && preg_match($filePathPattern, $mappedUri)) {
                $path = (string) preg_replace('/^file:/', '', $mappedUri);

                @unlink($path);
            }
        }
    }
}
