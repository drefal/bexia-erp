<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;

class CompanySwitchController extends Controller
{
    public function store(Request $request)
    {
        $request->validate(['slug' => ['required','string']]);

        $company = Company::where('slug', $request->slug)->firstOrFail();

        // guardamos selección y fijamos team para Spatie
        session(['company_id' => $company->id]);
        setPermissionsTeamId($company->id);

        return back()->with('status', 'Empresa cambiada a '.$company->name);
    }
}
