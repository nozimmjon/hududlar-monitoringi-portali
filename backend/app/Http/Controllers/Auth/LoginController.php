<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate(
            ['email' => ['required', 'string', 'email'], 'password' => ['required', 'string']],
            ['required' => ':attribute киритилмаган.', 'email' => 'Электрон почта нотўғри ёзилган.'],
            ['email' => 'Электрон почта', 'password' => 'Парол'],
        );

        $ok = Auth::attempt(
            ['email' => mb_strtolower($credentials['email']), 'password' => $credentials['password']],
            remember: true,
        );
        if (! $ok) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'Почта ёки парол нотўғри.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.roadmaps'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
