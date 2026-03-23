<?php

namespace Prosvirin\EnvSync\Classes;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;

class EnvComparator
{
    #[ArrayShape(['missing' => 'array', 'extra' => 'array', 'mismatch' => 'array', 'total_example' => 'array', 'total_env' => 'array'])]
    public function compare(array $example, array $env): array
    {
        $missing = [];
        $extra = [];
        $mismatch = [];

        foreach ($example as $key => $value) {
            if (! array_key_exists($key, $env)) {
                $missing[$key] = [
                    'example' => $value,
                    'suggested' => $value,
                ];
            } elseif ($env[$key] !== $value) {
                $mismatch[$key] = [
                    'example' => $value,
                    'env' => $env[$key],
                ];
            }
        }

        foreach ($env as $key => $value) {
            if (! array_key_exists($key, $example)) {
                $extra[$key] = $value;
            }
        }

        return [
            'missing' => $missing,
            'extra' => $extra,
            'mismatch' => $mismatch,
            'total_example' => $example,
            'total_env' => $env,
        ];
    }

    public function getMissingVariables(array $example, array $env): array
    {
        $missing = [];

        foreach ($example as $key => $value) {
            if (! array_key_exists($key, $env)) {
                $missing[$key] = $value;
            }
        }

        return $missing;
    }

    public function getExtraVariables(array $example, array $env): array
    {
        $extra = [];

        foreach ($env as $key => $value) {
            if (! array_key_exists($key, $example)) {
                $extra[$key] = $value;
            }
        }

        return $extra;
    }

    #[Pure]
    public function hasMissing(array $example, array $env): bool
    {
        return ! empty($this->getMissingVariables($example, $env));
    }

    #[Pure]
    public function hasExtra(array $example, array $env): bool
    {
        return ! empty($this->getExtraVariables($example, $env));
    }

    #[Pure]
    public function isSynchronized(array $example, array $env): bool
    {
        return empty($this->getMissingVariables($example, $env)) &&
            empty($this->getExtraVariables($example, $env));
    }
}
