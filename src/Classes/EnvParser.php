<?php

namespace Prosvirin\EnvSync\Classes;

use Illuminate\Support\Facades\File;

class EnvParser
{
    public function parse(string $path): array
    {
        if (! File::exists($path)) {
            return [];
        }

        $content = File::get($path);

        return $this->parseContent($content);
    }

    public function parseContent(string $content): array
    {
        $lines = explode("\n", $content);
        $variables = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            if (preg_match('/^([A-Za-z0-9_]+)=(.*)$/', $line, $matches)) {
                $key = $matches[1];
                $value = trim($matches[2]);

                if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
                    $value = substr($value, 1, -1);
                }
                if (str_starts_with($value, "'") && str_ends_with($value, "'")) {
                    $value = substr($value, 1, -1);
                }

                $variables[$key] = $value;
            }
        }

        return $variables;
    }

    public function replaceVariable(string $content, string $key, string $newValue): string
    {
        $lines = explode("\n", $content);
        $newLines = [];

        foreach ($lines as $line) {
            if (preg_match('/^'.preg_quote($key, '/').'=.*$/', trim($line))) {
                $newLines[] = "{$key}={$newValue}";
            } else {
                $newLines[] = $line;
            }
        }

        return implode("\n", $newLines);
    }

    public function removeVariable(string $content, string $key): string
    {
        $lines = explode("\n", $content);
        $newLines = [];

        foreach ($lines as $line) {
            if (! preg_match('/^'.preg_quote($key, '/').'=.*$/', trim($line))) {
                $newLines[] = $line;
            }
        }

        return implode("\n", $newLines);
    }

    public function addVariable(string $content, string $key, string $value): string
    {
        if (! str_ends_with($content, "\n") && ! empty($content)) {
            $content .= "\n";
        }

        if (! empty($content)) {
            $content .= "\n";
        }

        return $content."{$key}={$value}\n";
    }
}
