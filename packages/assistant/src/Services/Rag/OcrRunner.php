<?php

declare(strict_types=1);

namespace Enpii\Assistant\Services\Rag;

use RuntimeException;

final class OcrRunner
{
    public function run(string $absolutePath): string
    {
        // Stub: real implementation would shell out to tesseract / call cloud OCR.
        throw new RuntimeException('ocr_not_implemented');
    }
}
