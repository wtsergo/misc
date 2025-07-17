<?php

namespace Wtsergo\Misc\Helper;

use function Wtsergo\Misc\dtoMapper;

trait DtoTrait
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
            if (!$this->isUndefined($__name) && property_exists($this, $__name)) {
                if (!array_key_exists($__name, $args)) {
                    $args[$__name] = $this->{$__name}??null;
                }
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
            if (!property_exists($this, $__name)) continue;
            $array[$__name] = $this->{$__name};
        }
        return $array;
    }

    public static function tuneDbRow(array $dbRow): array
    {
        return $dbRow;
    }

    public function toDbRow(): array
    {
        $dbRow = $this->toArray();
        foreach ($dbRow as $key => &$value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_THROW_ON_ERROR);
            }
        }
        unset($value);
        return $dbRow;
    }

    public function toArray(bool $skipUnsupported = false): array
    {
        $array = [];
        foreach (self::parameters(static::class) as $parameter) {
            $__name = $parameter->getName();
            if (!property_exists($this, $__name)) continue;
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
        $dto = dtoMapper(allowSuperfluousKeys: true)->map(
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

    public function __sleep(): array
    {
        return array_map(
            fn (\ReflectionParameter $parameter) => $parameter->name,
            self::parameters(static::class)
        );
    }

    private static function reflector($class)
    {
        static $reflectorCache = [];
        if (!isset($reflectorCache[$class])) {
            $reflectorCache[$class] = new \ReflectionClass($class);
        }
        return $reflectorCache[$class];
    }

    private static function parameters($class)
    {
        /** @var array<class-string, \ReflectionParameter[]> */
        static $ctorParametersCache = [];
        if (!isset($ctorParametersCache[$class])) {
            $ctorParametersCache[$class] = self::reflector($class)
                ->getConstructor()?->getParameters() ?? [];
        }
        return $ctorParametersCache[$class];
    }
}
