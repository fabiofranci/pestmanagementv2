<?php

namespace App\Http\Middleware;

use Filament\Http\Middleware\AuthenticateSession as Middleware;

class AuthenticatePanelSession extends Middleware
{
    protected function redirectTo($request): ?string
    {
        return route('login');
    }
}
