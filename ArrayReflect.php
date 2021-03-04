<?php declare(strict_types=1);

/*
 * This file is part of the VV package.
 *
 * (c) Volodymyr Sarnytskyi <v00v4n@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace VV\Utils;

use JetBrains\PhpStorm\Pure;

/**
 * Class ArrayReflect
 */
class ArrayReflect implements \IteratorAggregate, \JsonSerializable, \Countable {

    private ?array $array;

    private ?string $getterTypeExceptionClass = null;

    /**
     * ArrayReflect constructor.
     *
     * @param array|null $array
     */
    public function __construct(&$array = null) {
        if (!is_array($array)) $array = [];
        $this->array = &$array;
    }

    /**
     * @param mixed ...$values
     *
     * @return bool
     */
    #[Pure]
    public function has(mixed ...$values): bool {
        foreach ($values as $v) {
            if (!in_array($v, $this->array, true)) return false;
        }

        return true;
    }

    /**
     * @param string|int ...$keys
     *
     * @return bool
     */
    #[Pure]
    public function hasKey(string|int ...$keys): bool {
        $array = $this->array;

        foreach ($keys as $k) {
            if (!array_key_exists($k, $array)) return false;
        }

        return true;
    }

    /**
     * @param string|int|string[]|int[]|null $key
     * @param mixed                          $default
     * @param bool|null                      $found
     *
     * @return mixed
     */
    public function get(string|int|array $key = null, mixed $default = null, &$found = null): mixed {
        if ($key === null) return $this->array;
        if (!is_array($key)) {
            $array = $this->array;

            return ($found = array_key_exists($key, $array)) ? $array[$key] : $default;
        }

        $results = [];
        $found = true;
        foreach ($key as $k => $v) {
            $self = $this;
            if (is_array($v)) {
                $self = self::cast($this->get($k) ?: []);
            }

            $results[] = $self->get($v, $default, $f);
            if (!$f) $found = false;
        }

        return $results;
    }

    /**
     * @param string[]         $keys
     * @param array[]|scalar[] $defaults
     *
     * @return array
     */
    public function aget(array $keys, mixed ...$defaults): array {
        $arrays = [$this->array, ...$defaults, null];

        $total = [];
        foreach ($arrays as $arr) {
            if ($notarr = !is_array($arr)) {
                $arr = array_fill_keys($keys, $arr);
            }

            $total += $arr;
            if ($notarr) break;
        }

        $result = [];
        foreach ($keys as $k) $result[$k] = $total[$k];

        return $result;
    }

    /**
     * Fetches value(s) by $path
     *
     * @param string[]|string[][] ...$path
     *
     * @return mixed
     */
    public function xget(string|array ...$path): mixed {
        $cur = $this;
        $key = null;
        $found = null;
        foreach ($path as $i => $key) {
            if (!$cur instanceof self) {
                if (!is_iterable($cur)) {
                    $cur = null;
                    $found = false;
                    break;
                }
                $cur = $this->createLikeThis($cur);
            }

            $cur = $cur->get($key, null, $found);
            if (is_array($key)) $found = true;
            if (!$found) break;
        }
        if ($found === false && is_array($key = end($path))) {
            return array_fill(0, count($key), null);
        }

        return $cur;
    }

    /**
     * @param string ...$path
     *
     * @return mixed
     */
    public function &ref(string ...$path): mixed {
        $cur = &$this->array;
        foreach ($path as $key) {
            if (!is_array($cur)) $cur = [];
            $cur = &$cur[$key];
        }

        return $cur;
    }

    /**
     * @param string ...$path
     *
     * @return self
     */
    public function iref(string ...$path): self {
        $ref = &$this->ref(...$path);
        if ($ref instanceof self) return $ref;

        return new self($ref);
    }


    /**
     * @param string|int $key
     * @param bool       $trim
     *
     * @return string|null
     */
    public function string(string|int $key, bool $trim = true): ?string {
        $scalar = $this->scalar($key);
        if (is_string($scalar) || $scalar === null) return $scalar;

        if (is_bool($scalar)) {
            throw $this->createGetterTypeException("Field \"$key\" is not string-convertible");
        }

        $scalar = (string)$scalar;
        if ($trim) $scalar = trim($scalar);

        return $scalar;
    }

    /**
     * @param string|int $key
     *
     * @return int|null
     */
    public function int(string|int $key): ?int {
        $scalar = $this->scalar($key);
        if (is_int($scalar) || $scalar === null) return $scalar;

        $int = (int)$scalar;

        $error = (function () use ($scalar, $int) {
            if (is_bool($scalar)) return true;

            $scalar = (string)$scalar;
            if (!preg_match('/^\d+$/', $scalar)) return true;

            return (string)$int !== preg_replace('/^0+(?=.)/', '', $scalar);
        })(); //@codeCoverageIgnore

        if ($error) {
            throw $this->createGetterTypeException("Field \"$key\" is not int-convertible");
        }

        return $int;
    }

    /**
     * @param string|int $key
     *
     * @return float|null
     */
    public function float(string|int $key): ?float {
        $scalar = $this->scalar($key);
        if (is_float($scalar) || $scalar === null) return $scalar;

        $float = (float)$scalar;

        $error = (function () use ($scalar, $float) {
            if (is_bool($scalar)) return true;

            $scalar = (string)$scalar;
            if (!preg_match('/^\d+(\.\d+)?$/', $scalar)) return true;

            return false;
        })(); //@codeCoverageIgnore

        if ($error) {
            throw $this->createGetterTypeException("Field \"$key\" is not float-convertible");
        }

        return $float;
    }

    /**
     * @param string|int $key
     *
     * @return bool|null
     */
    public function bool(string|int $key): ?bool {
        $scalar = $this->scalar($key);
        if (is_bool($scalar) || $scalar === null) return $scalar;

        $str = (string)$scalar;
        if ($str !== '' && $str !== '0' && $str !== '1') {
            throw $this->createGetterTypeException("Field \"$key\" is not bool-convertible");
        }

        return (bool)$str;
    }

    /**
     * @param string|int $key
     *
     * @return string|int|float|bool|null
     */
    public function scalar(string|int $key): string|int|float|bool|null {
        $value = $this->get($key);
        if (!is_scalar($value) && $value !== null) {
            throw $this->createGetterTypeException("Field \"$key\" is not scalar");
        }

        return $value;
    }


    /**
     * Returns array value by key
     *
     * @param string|int $key
     *
     * @return array|null
     */
    public function array(string|int $key): ?array {
        $value = $this->get($key);
        if (!is_array($value) && $value !== null) {
            throw $this->createGetterTypeException("Field \"$key\" is not array");
        }

        return $value;
    }

    /**
     * @param string|int $key
     *
     * @return ArrayReflect|null
     */
    public function arrayReflect(string|int $key): ?self {
        if (!$array = $this->array($key)) return null;

        return $this->createLikeThis($array);
    }

    /**
     * @param string|int|null $key
     *
     * @return string[]
     */
    public function stringIterator(string|int $key = null): iterable {
        return $this->createTypedIterator(function (self $ref, $k) {
            return $ref->string($k);
        }, $key);
    }

    /**
     * @param string|int|null $key
     *
     * @return int[]
     */
    public function intIterator(string|int $key = null): iterable {
        return $this->createTypedIterator(function (self $ref, $k) {
            return $ref->int($k);
        }, $key);
    }

    /**
     * @param string|int|null $key
     *
     * @return float[]
     */
    public function floatIterator(string|int $key = null): iterable {
        return $this->createTypedIterator(function (self $ref, $k) {
            return $ref->float($k);
        }, $key);
    }

    /**
     * @param string|int|null $key
     *
     * @return bool[]
     */
    public function boolIterator(string|int $key = null): iterable {
        return $this->createTypedIterator(function (self $ref, $k) {
            return $ref->bool($k);
        }, $key);
    }

    /**
     * @param string|int|null $key
     *
     * @return array string[]|int[]|float[]|bool[]|null[]
     */
    public function scalarIterator(string|int $key = null): iterable {
        return $this->createTypedIterator(function (self $ref, $k) {
            return $ref->scalar($k);
        }, $key);
    }

    /**
     * @param string|int|null $key
     *
     * @return array[]
     */
    public function arrayIterator(string|int $key = null): iterable {
        return $this->createTypedIterator(function (self $ref, $k) {
            return $ref->array($k);
        }, $key);
    }

    /**
     * @param string|int|null $key
     *
     * @return self[]
     */
    public function arrayReflectIterator(string|int $key = null): iterable {
        return $this->createTypedIterator(function (self $ref, $k) {
            return $ref->arrayReflect($k);
        }, $key);
    }

    /**
     * @param string|int|array $key
     * @param mixed            $value
     *
     * @return $this
     */
    public function set(string|int|array $key, $value = null): self {
        if (is_array($key)) {
            $this->array = $key;
        } else {
            $this->array[$key] = $value;
        }

        return $this;
    }

    public function push(...$values): self {
        $array = &$this->array;
        foreach ($values as $v) {
            $array[] = $v;
        }

        return $this;
    }

    /** @noinspection PhpPureAttributeCanBeAddedInspection */
    public function merge(array $value, bool $recursive = false): self {
        $array = &$this->array;
        if ($recursive) {
            $array = array_merge_recursive($array, $value);
        } else {
            $array = array_merge($array, $value);
        }

        return $this;
    }

    public function unset(string|int ...$keys): self {
        $array = &$this->array;
        foreach ($keys as $key) unset($array[$key]);

        return $this;
    }

    public function clear(): self {
        $this->array = [];

        return $this;
    }

    public function isEmpty(): bool {
        return empty($this->array);
    }

    #[Pure]
    public function length(): int {
        return count($this->array);
    }

    /**
     * @inheritdoc
     */
    public function getIterator(): iterable {
        foreach ($this->array as $k => $v) yield $k => $v;
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize() {
        return $this->array;
    }

    /**
     * @inheritdoc
     */
    #[Pure]
    public function count(): int {
        return \count($this->array);
    }

    /**
     * @param string|null $class
     *
     * @return $this
     */
    public function setGetterTypeExceptionClass(?string $class): self {
        $this->getterTypeExceptionClass = $class;

        return $this;
    }

    /**
     * @param string $message
     *
     * @return \RuntimeException
     */
    private function createGetterTypeException(string $message): mixed {
        if ($class = $this->getterTypeExceptionClass) return new $class($message);

        return new \UnexpectedValueException($message);
    }

    private function createLikeThis($array): self {
        return ArrayReflect::cast($array)
            ->setGetterTypeExceptionClass($this->getterTypeExceptionClass);
    }

    /**
     * @param callable        $clbk
     * @param string|int|null $key
     *
     * @return iterable
     */
    private function createTypedIterator(callable $clbk, $key = null): iterable {
        $ref = $key ? $this->arrayReflect($key) : $this;
        if (!$ref) return;

        foreach ($ref->array as $k => $v) {
            yield $k => $clbk($ref, $k);
        }
    }

    /**
     * @param ArrayReflect|array|mixed $array
     *
     * @return self
     */
    public static function cast(mixed $array): static {
        if ($array instanceof self) return $array;
        if ($array instanceof \Traversable) {
            $array = iterator_to_array($array);
        } elseif (!is_array($array)) {
            $array = (array)$array;
        }

        return new self($array);
    }
}
