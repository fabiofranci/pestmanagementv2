<?php

namespace App\Http\Middleware;

use Filament\Http\Middleware\Authenticate as Middleware;

class AuthenticatePanel extends Middleware
{
    protected function redirectTo($request): ?string
    {
        return route('login');
    }
}
