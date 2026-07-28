<?php

declare(strict_types=1);

namespace App\Services\Billing;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final readonly class TripayClient
{
    public function createTransaction(
        int $amount,
        string $merchantRef,
        string $customerName,
        ?string $customerEmail,
        array $orderItems,
        ?string $returnUrl = null,
    ): array {
        $merchantCode = (string) config('tripay.merchant_code');
        $privateKey = (string) config('tripay.private_key');
        $apiKey = (string) config('tripay.api_key');
        $method = (string) config('tripay.default_method', 'QRIS');

        if ($merchantCode === '' || $privateKey === '' || $apiKey === '') {
            throw new RuntimeException('Konfigurasi Tripay belum lengkap.');
        }

        $signature = hash_hmac('sha256', $merchantCode.$merchantRef.$amount, $privateKey);

        $payload = [
            'method' => $method,
            'merchant_ref' => $merchantRef,
            'amount' => $amount,
            'customer_name' => $customerName,
            'customer_email' => $customerEmail ?: 'billing@'.parse_url((string) config('app.url'), PHP_URL_HOST),
            'order_items' => $orderItems,
            'return_url' => $returnUrl ?: (string) config('app.url'),
            'signature' => $signature,
        ];

        $callbackUrl = (string) config('tripay.callback_url', '');
        if ($callbackUrl !== '') {
            $payload['callback_url'] = $callbackUrl;
        }

        try {
            $response = Http::baseUrl($this->baseUrl())
                ->withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->post('/transaction/create', $payload)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            throw new RuntimeException('Gagal membuat transaksi Tripay: '.$exception->getMessage(), previous: $exception);
        }

        if (! ($response['success'] ?? false)) {
            throw new RuntimeException('Tripay menolak transaksi: '.($response['message'] ?? 'unknown'));
        }

        return $response['data'] ?? [];
    }

    public function verifyCallbackSignature(string $callbackSignature, string $jsonBody): bool
    {
        $privateKey = (string) config('tripay.private_key');
        if ($privateKey === '' || $callbackSignature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $jsonBody, $privateKey);

        return hash_equals($expected, $callbackSignature);
    }

    private function baseUrl(): string
    {
        return config('tripay.mode') === 'production'
            ? 'https://tripay.co.id/api'
            : 'https://tripay.co.id/api-sandbox';
    }
}
