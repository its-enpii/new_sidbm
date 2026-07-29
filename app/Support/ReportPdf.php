<?php

declare(strict_types=1);

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportPdf
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function stream(string $view, array $data, string $filename, string $orientation = 'portrait'): Response|StreamedResponse
    {
        $pdf = Pdf::loadView($view, $data)->setPaper('a4', $orientation);

        return $pdf->stream($filename);
    }
}
