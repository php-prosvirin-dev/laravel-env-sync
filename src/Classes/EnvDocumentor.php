<?php

namespace Prosvirin\EnvSync\Classes;

use Illuminate\Support\Facades\File;

class EnvDocumentor
{
    public function generateDocumentation(string $examplePath, array $diff): string
    {
        $date = date('Y-m-d H:i:s');
        $content = "# Environment Variables Documentation\n\n";
        $content .= "Generated: {$date}\n\n";
        $content .= "## Variables\n\n";
        $content .= "| Variable | Default Value | Status |\n";
        $content .= "|----------|---------------|--------|\n";

        foreach ($diff['total_example'] as $key => $value) {
            $status = array_key_exists($key, $diff['missing']) ? '❌ Missing in .env' : '✅ OK';
            $content .= "| {$key} | {$value} | {$status} |\n";
        }

        if (! empty($diff['extra'])) {
            $content .= "\n## Extra Variables in .env\n\n";
            $content .= "These variables exist in .env but are not documented in .env.example:\n\n";
            foreach ($diff['extra'] as $key => $value) {
                $content .= "- `{$key}` = `{$value}`\n";
            }
        }

        if (! empty($diff['mismatch'])) {
            $content .= "\n## Mismatched Values\n\n";
            $content .= "Variables with different values between files:\n\n";
            foreach ($diff['mismatch'] as $key => $values) {
                $content .= "- `{$key}`:\n";
                $content .= "  - .env.example: `{$values['example']}`\n";
                $content .= "  - .env: `{$values['env']}`\n";
            }
        }

        $outputPath = storage_path('docs/env-documentation.md');

        if (! File::exists(dirname($outputPath))) {
            File::makeDirectory(dirname($outputPath), 0755, true);
        }

        File::put($outputPath, $content);

        return $outputPath;
    }

    public function generateReport(array $diff): array
    {
        $report = [];

        if (! empty($diff['missing'])) {
            $report['missing'] = $diff['missing'];
        }

        if (! empty($diff['extra'])) {
            $report['extra'] = $diff['extra'];
        }

        if (! empty($diff['mismatch'])) {
            $report['mismatch'] = $diff['mismatch'];
        }

        return $report;
    }
}
