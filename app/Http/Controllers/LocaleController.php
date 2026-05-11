<?php


namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class LocaleController extends Controller
{
public function update(Request $request)
{
$supported = ['es','en','pt'];


$request->validate([
'lang' => ['required', 'in:' . implode(',', $supported)],
]);


$locale = (string) $request->input('lang');


// Sesión: efecto inmediato
session(['locale' => $locale]);


// Persistencia en el usuario autenticado
if (Auth::check()) {
$user = Auth::user();
$user->forceFill(['locale' => $locale])->save();
}


return back();
}
}