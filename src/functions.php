<?php

namespace Wtsergo\Misc;

use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\Mapper\Tree\Node;
use CuyZ\Valinor\Mapper\Tree\NodeTraverser;
use CuyZ\Valinor\Mapper\TreeMapper;
use CuyZ\Valinor\MapperBuilder;
use CuyZ\Valinor\Normalizer\Format;
use CuyZ\Valinor\Normalizer\Normalizer;
use Psr\Log\LoggerInterface;
use function assert;

function dtoMapper(
    bool $enableFlexibleCasting = true,
    bool $allowPermissiveTypes = true,
    bool $allowSuperfluousKeys = false,
    bool $allowUndefinedValues = false,
    bool $allowScalarValueCasting = false,
): TreeMapper
{
    $key = sprintf(
        '%d-%d-%d-%d-%d',
        $enableFlexibleCasting,
        $allowPermissiveTypes,
        $allowSuperfluousKeys,
        $allowUndefinedValues,
        $allowScalarValueCasting
    );
    static $requestMapper = [];
    return $requestMapper[$key] ??= buildDtoMapper(
        $enableFlexibleCasting,
        $allowPermissiveTypes,
        $allowSuperfluousKeys,
        $allowUndefinedValues,
        $allowScalarValueCasting
    );
}

function buildDtoMapper(
    bool $enableFlexibleCasting = true,
    bool $allowPermissiveTypes = true,
    bool $allowSuperfluousKeys = false,
    bool $allowUndefinedValues = false,
    bool $allowScalarValueCasting = false,
): TreeMapper
{
    $mapper = (new MapperBuilder);
    $enableFlexibleCasting && ($mapper = $mapper->enableFlexibleCasting());
    $allowPermissiveTypes && ($mapper = $mapper->allowPermissiveTypes());
    $allowSuperfluousKeys && ($mapper = $mapper->allowSuperfluousKeys());
    $allowUndefinedValues && ($mapper = $mapper->allowUndefinedValues());
    $allowScalarValueCasting && ($mapper = $mapper->allowScalarValueCasting());
    return $mapper->mapper();
}

function dtoNormalizer(
    Format $format = null
): Normalizer
{
    $format = $format ?? Format::array();
    $key = $format->type();
    static $requestNormalizer = [];
    return $requestNormalizer[$key] ??= buildDtoNormalizer($format);
}

function buildDtoNormalizer(
    Format $format = null,
): Normalizer
{
    $mapperBuilder = (new MapperBuilder);
    return $mapperBuilder->normalizer($format ?? Format::array());
}

/**
 * @param MappingError $error
 * @return string[]
 */
function extractDtoMappingErrors(MappingError $error, int $shiftPath = 0, ?LoggerInterface $logger = null): array
{
    /** @var iterable<Node> $nodes */
    $nodes = (new NodeTraverser(
        fn(Node $node) => $node
    ))->traverse($error->node());

    $errors = [];
    foreach ($nodes as $node) {
        if ($node->isRoot()) {
            assert($logger?->debug(sprintf('Mapping failed for %s', $node->type())) || true);
        }
        if (!$node->isValid() && !empty($node->messages())) {
            $shiftedPath = explode('.', $node->path());
            $shiftedPath = array_slice($shiftedPath, $shiftPath);
            if (!$node->isRoot() && !empty($shiftedPath)) {
                $errors[] = sprintf('Invalid value for %s', implode('.', $shiftedPath));
            }
            $__internal = [];
            foreach ($node->messages() as $message) {
                $__internal[] = (string)$message;
                if ($node->isRoot() && str_contains($message, 'Unexpected key')
                    || !$node->isRoot() && empty($shiftedPath)
                ) {
                    $errors[] = (string)$message;
                }
            }
            if (!empty($__internal)) {
                assert($logger?->debug(sprintf(
                        'Invalid value for %s: %s',
                        $node->path(), implode(', ', $__internal)
                    )) || true);
            }
        }
    }
    return $errors;
}


