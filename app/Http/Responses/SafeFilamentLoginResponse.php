<?php

namespace App\Http\Responses;

use App\Support\Security\SafeAdminUrl;
use Filament\Http\Responses\Auth\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Livewire\Features\SupportRedirects\Redirector;

class SafeFilamentLoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse | Redirector
    {
        if ($request instanceof Request) {
            $request->session()->forget('url.intended');
            $request->session()->forget('intended');

            return redirect()->to(SafeAdminUrl::forUser($request->user()));
        }

        return redirect()->to('/admin');
    }
}
