<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Admin/Settings/Edit', [
            'freeShippingThreshold' => Setting::get('free_shipping_threshold'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'free_shipping_threshold' => 'nullable|numeric|min:0',
        ]);

        Setting::set('free_shipping_threshold', $request->free_shipping_threshold);

        return back()->with('success', 'Configuración actualizada.');
    }
}
