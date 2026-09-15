<?php

declare(strict_types=1);

namespace App\Assistant;

use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;

/**
 * Menutup permukaan endpoint bawaan milik enpii/assistant.
 *
 * AssistantServiceProvider::boot() memanggil loadRoutesFrom() pada berkas routes/api.php
 * milik package, sehingga sembilan rute polos (chat, persona, confirmations, receipt pesan
 * dan receipt percakapan) terdaftar tanpa prefix dan tanpa middleware apa pun. Host sudah
 * me-require berkas rute yang sama di bawah /assistant/* dengan auth + tenant +
 * subscription.active + feature:ai, jadi salinan polos tersebut murni celah: traffic anonim
 * dapat memanggil LLM, melewati gerbang AI per-tenant, serta mengonfirmasi atau membatalkan
 * aksi assistant.
 *
 * Package tidak memberi tahu untuk melewati pemuatan rute, maka guard membuang rute itu
 * setelah seluruh provider boot - momen pertama ketika kedua set rute tersedia. Identifikasi
 * memakai namespace controller package plus URI tanpa mount, sehingga rute polos dari rilis
 * package mendatang ikut tertutup (fail-closed). Widget tetap berfungsi karena hanya salinan
 * tanpa prefix yang dibuang.
 */
final class AssistantNativeRouteGuard
{
    private const PACKAGE_CONTROLLER_NAMESPACE = 'Enpii\\Assistant\\Http\\Controllers\\';

    /**
     * Prefix yang dipakai routes/web.php saat me-mount berkas rute package.
     */
    private const HOST_MOUNT_PREFIX = 'assistant';

    public function __construct(private readonly Router $router) {}

    /**
     * Buang rute bawaan package dari koleksi rute aktif.
     *
     * @return int Jumlah rute yang dibuang
     */
    public function disable(): int
    {
        if (config('assistant.register_native_routes') === true) {
            return 0;
        }

        $kept = [];
        $removed = 0;

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            if ($this->isNative($route)) {
                $removed++;

                continue;
            }

            $kept[] = $route;
        }

        if ($removed === 0) {
            return 0;
        }

        $this->router->setRoutes($this->rebuild($kept));

        return $removed;
    }

    /**
     * Daftar rute bawaan package yang masih terdaftar. Bersifat baca-saja, dipakai tes.
     *
     * @return array<int, string> contoh: ["POST|HEAD chat", "GET|HEAD persona"]
     */
    public function detect(): array
    {
        $found = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            if ($this->isNative($route)) {
                $found[] = implode('|', $route->methods()).' '.$route->uri();
            }
        }

        return $found;
    }

    private function isNative(Route $route): bool
    {
        $controller = (string) ($route->getAction('controller') ?? '');

        if ($controller === '' || ! str_starts_with($controller, self::PACKAGE_CONTROLLER_NAMESPACE)) {
            return false;
        }

        return ! str_starts_with($route->uri(), self::HOST_MOUNT_PREFIX.'/');
    }

    /**
     * Bangun ulang koleksi agar tabel pencarian (nama, action, method => URI) tetap konsisten.
     *
     * @param  array<int, Route>  $routes
     */
    private function rebuild(array $routes): RouteCollection
    {
        $collection = new RouteCollection;

        foreach ($routes as $route) {
            $collection->add($route);
        }

        $collection->refreshNameLookups();
        $collection->refreshActionLookups();

        return $collection;
    }
}
