<?php

declare(strict_types=1);

namespace Enpii\SidbmAssistant\Support;

use Illuminate\Http\Request;

final class NullSessionTokenAuthorizer extends SessionTokenAuthorizer
{
    public function allows(mixed $user): bool
    {
        return false;
    }

    public function externalId(mixed $user): string
    {
        return '';
    }

    public function displayName(mixed $user): ?string
    {
        return null;
    }

    public function resolveTenantCode(Request $request): ?string
    {
        return null;
    }

    public function denialReason(mixed $user): string
    {
        return 'assistant: bind a SessionTokenAuthorizer to enable this endpoint';
    }
}