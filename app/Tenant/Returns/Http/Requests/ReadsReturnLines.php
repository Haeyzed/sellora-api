<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Requests;

use Illuminate\Support\ValidatedInput;

/**
 * Reads a return request's validated lines field by field, each as the type its rules guarantee.
 */
trait ReadsReturnLines
{
    /**
     * @return array<string, string> The variant sent in place of each line, by order line public ID; only lines that name one.
     */
    public function replacements(): array
    {
        $validated = $this->safe();
        $replacements = [];

        foreach ($this->linePositions($validated) as $position) {
            $replacement = $validated->string("lines.{$position}.replacement")->value();

            if ($replacement !== '') {
                $replacements[$this->lineOf($validated, $position)] = $replacement;
            }
        }

        return $replacements;
    }

    /**
     * Where each validated line sits in the request, to read its fields by key; none when lines were left out.
     *
     * @return list<int|string>
     */
    private function linePositions(ValidatedInput $validated): array
    {
        return array_keys($validated->array('lines'));
    }

    /**
     * The order line's public ID that a validated line names.
     */
    private function lineOf(ValidatedInput $validated, int|string $position): string
    {
        return $validated->string("lines.{$position}.line")->value();
    }
}
