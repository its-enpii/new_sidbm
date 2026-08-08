<?php

declare(strict_types=1);

namespace Enpii\Assistant\Services\Rag;

use RuntimeException;
use Smalot\PdfParser\Parser as PdfParser;

final class DocumentLoader
{
    public function __construct(
        private readonly ?OcrRunner $ocr = null,
    ) {
    }

    /**
     * @throws RuntimeException when file can't be read
     */
    public function load(string $absolutePath, ?string $declaredFormat = null): string
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException('file_not_found');
        }

        $ext = strtolower($declaredFormat ?: (string) pathinfo($absolutePath, PATHINFO_EXTENSION));

        return match ($ext) {
            'txt', 'md' => (string) file_get_contents($absolutePath),
            'pdf' => $this->loadPdf($absolutePath),
            'image', 'jpg', 'jpeg', 'png' => $this->loadImage($absolutePath),
            default => (string) file_get_contents($absolutePath),
        };
    }

    private function loadPdf(string $path): string
    {
        $parser = new PdfParser();
        $pdf = $parser->parseFile($path);
        $text = $pdf->getText();

        return trim($text);
    }

    private function loadImage(string $path): string
    {
        if ($this->ocr === null) {
            throw new RuntimeException('ocr_unavailable');
        }

        return $this->ocr->run($path);
    }
}
