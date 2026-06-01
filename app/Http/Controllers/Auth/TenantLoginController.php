<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TenantLoginController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->to($this->panelUrl());
        }

        return view('auth.tenant-login');
    }

    public function redirectFromPanel(): RedirectResponse
    {
        return redirect()->route('login');
    }

    public function store(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->to($this->panelUrl());
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Le credenziali inserite non sono valide.',
            ]);
        }

        $request->session()->regenerate();

        $user = $request->user();
        $panel = Filament::getPanel('admin');

        if (! $user || ! $panel || ! $user->canAccessPanel($panel)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Questo account non puo accedere al pannello.',
            ]);
        }

        return redirect()->intended($this->panelUrl());
    }

    protected function panelUrl(): string
    {
        return Filament::getPanel('admin')->getUrl() ?? url('/admin');
    }
}
