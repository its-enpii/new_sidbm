<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Website\Services\PublicSiteContentService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ServeBotFriendlyHtml
{
    private const BOT_PATTERN = '/Googlebot|Bingbot|DuckDuckBot|Slurp|facebookexternalhit|Twitterbot|WhatsApp|TelegramBot|LinkedInBot|discordbot|embedly|quora|vkShare/i';

    public function __construct(
        private PublicSiteContentService $content,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') || preg_match(self::BOT_PATTERN, (string) $request->userAgent()) !== 1) {
            return $next($request);
        }

        $site = $this->content->tenantSite();
        $view = match ($request->path()) {
            '/' => $site === null ? $this->vendorHomeView() : $this->tenantHomeView($site),
            'berita' => $site === null ? $this->vendorHomeView() : $this->blogIndexView($site, $request),
            'kontak' => $site === null ? $this->vendorHomeView() : $this->contactView($site),
            default => null,
        };

        if ($view !== null) {
            return response()->view($view['name'], $view['data'])->header('X-Robots-HTML', 'bot-friendly');
        }

        if ($site !== null && $request->is('berita/*')) {
            $slug = $request->route('slug') ?? basename($request->path());
            $post = is_string($slug) ? $this->content->post($slug) : null;

            if ($post !== null) {
                return response()->view('public.bot.post', [
                    'site' => $site,
                    'post' => $this->content->postData($post),
                ])->header('X-Robots-HTML', 'bot-friendly');
            }
        }

        if ($site !== null && $request->is('p/*')) {
            $slug = $request->route('slug') ?? basename($request->path());
            $page = is_string($slug) ? $this->content->page($slug) : null;

            if ($page !== null) {
                return response()->view('public.bot.page', [
                    'site' => $site,
                    'page' => $this->content->pageData($page),
                ])->header('X-Robots-HTML', 'bot-friendly');
            }
        }

        return $next($request);
    }

    /**
     * @param  array<string, mixed>  $site
     * @return array{name: string, data: array<string, mixed>}
     */
    private function tenantHomeView(array $site): array
    {
        return [
            'name' => 'public.bot.tenant-home',
            'data' => ['site' => $site],
        ];
    }

    /**
     * @param  array<string, mixed>  $site
     * @return array{name: string, data: array<string, mixed>}
     */
    private function blogIndexView(array $site, Request $request): array
    {
        return [
            'name' => 'public.bot.blog-index',
            'data' => [
                'site' => $site,
                ...$this->content->posts(trim((string) $request->query('q', ''))),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $site
     * @return array{name: string, data: array<string, mixed>}
     */
    private function contactView(array $site): array
    {
        return [
            'name' => 'public.bot.contact',
            'data' => [
                'site' => $site,
                'settings' => $this->content->settings(),
            ],
        ];
    }

    /**
     * @return array{name: string, data: array<string, mixed>}
     */
    private function vendorHomeView(): array
    {
        return [
            'name' => 'public.bot.vendor-home',
            'data' => ['name' => config('app.name')],
        ];
    }
}
