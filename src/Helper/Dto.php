<?php

namespace Wtsergo\Misc\Helper;

use CuyZ\Valinor\Mapper\TreeMapper;
use CuyZ\Valinor\MapperBuilder;
use Unirgy\Core\Dto\ValidationException;

trait Dto
{
    /**
     * @return class-string
     */
    public function className(): string
    {
        return static::class;
    }

    public function cloneWith(...$args): static
    {
        return $this->_with(true, ...$args);
    }

    public function with(...$args): static
    {
        return $this->_with(false, ...$args);
    }

    protected function _with(bool $clone, ...$args): static
    {
        foreach (self::parameters(static::class) as $parameter) {
            $__name = $parameter->getName();
            if (!$this->isUndefined($__name)) {
                $args[$__name] ??= $this->{$__name};
            }
        };
        return $clone ? new static(...$args) : static::fromArray($args);
    }

    public function children(bool $skipUndefined=true): array
    {
        $array = [];
        foreach (self::parameters(static::class) as $parameter) {
            $__name = $parameter->getName();
            if ($this->isUndefined($__name) && $skipUndefined) continue;
            $array[$__name] = $this->{$__name};
        }
        return $array;
    }

    public function toArray(bool $skipUnsupported = false): array
    {
        $array = [];
        foreach (self::parameters(static::class) as $parameter) {
            $__name = $parameter->getName();
            if ($this->isUndefined($__name)) continue;
            $value = $this->{$__name};
            if (is_object($value)) {
                if ($value instanceof \BackedEnum) {
                    $array[$__name] = $value->value;
                } elseif (method_exists($value, 'toArray')) {
                    $array[$__name] = $value->toArray();
                } elseif (!$skipUnsupported) {
                    throw new \RuntimeException(
                        sprintf('Unsupported property "%s" of "%s"', $__name, get_class($value))
                    );
                }
            } elseif (is_scalar($value) || is_array($value) || is_null($value)) {
                $array[$__name] = $value;
            }
        }
        return $array;
    }

    protected array $undefined = [];
    public function isUndefined(string $parameter, ?bool $flag=null): bool
    {
        $isUndefined = array_key_exists($parameter, $this->undefined);
        if ($flag !== null) {
            if ($flag) {
                $this->undefined[$parameter] = $isUndefined;
            } else {
                unset($this->undefined[$parameter]);
            }
        }
        return $isUndefined;
    }

    public static function fromArray(array $array): static
    {
        $undefined = [];
        foreach (self::parameters(static::class) as $parameter) {
            if (!array_key_exists($parameter->getName(), $array)
                && $parameter->allowsNull()
            ) {
                $undefined[] = $parameter->getName();
            }
        };
        $dto = static::dtoMapper()->map(
            static::class,
            $array
        );
        $dto->undefined = array_flip($undefined);
        return $dto;
    }

    public static function fromArgs(...$args): static
    {
        return static::fromArray($args);
    }

    public static function dtoMapper(): TreeMapper
    {
        static $dtoMapper = null;
        $dtoMapper ??= (new MapperBuilder)
            ->enableFlexibleCasting()
            ->allowPermissiveTypes()
            ->allowSuperfluousKeys()
            ->mapper();
        return $dtoMapper;
    }

    private static function parameters($class)
    {
        /** @var array<class-string, \ReflectionParameter[]> */
        static $ctorParametersCache = [];
        if (!isset($ctorParametersCache[$class])) {
            $ctorParametersCache[$class] = (new \ReflectionClass($class))
                ->getConstructor()->getParameters();
        }
        return $ctorParametersCache[$class];
    }
}
