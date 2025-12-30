<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductOfferController extends Controller
{
    /**
     * Crear o actualizar una oferta para un producto
     */
    public function store(Request $request, Product $product): RedirectResponse
    {
        $request->validate([
            'offer_price' => 'required|numeric|min:0.01|lt:' . $product->price,
            'start_date' => 'nullable|date|after_or_equal:today',
            'end_date' => 'nullable|date|after:start_date',
        ], [
            'offer_price.lt' => 'El precio de oferta debe ser menor al precio original.',
            'start_date.after_or_equal' => 'La fecha de inicio no puede ser anterior a hoy.',
            'end_date.after' => 'La fecha de fin debe ser posterior a la fecha de inicio.',
        ]);

        // Desactivar ofertas previas
        $product->offers()->where('is_active', true)->update(['is_active' => false]);

        // Crear nueva oferta
        $product->offers()->create([
            'offer_price' => $request->offer_price,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'is_active' => true,
        ]);

        return back()->with('success', 'Oferta creada exitosamente.');
    }

    /**
     * Actualizar una oferta existente
     */
    public function update(Request $request, ProductOffer $offer): RedirectResponse
    {
        $request->validate([
            'offer_price' => 'required|numeric|min:0.01|lt:' . $offer->product->price,
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'is_active' => 'boolean',
        ], [
            'offer_price.lt' => 'El precio de oferta debe ser menor al precio original.',
            'end_date.after' => 'La fecha de fin debe ser posterior a la fecha de inicio.',
        ]);

        // Determinar si la oferta debe estar activa
        $isActive = $request->boolean('is_active', true);
        
        // Si se está activando esta oferta, desactivar otras ofertas activas del mismo producto
        if ($isActive && !$offer->is_active) {
            $offer->product->offers()
                ->where('id', '!=', $offer->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }

        // Actualizar la oferta
        $offer->update([
            'offer_price' => $request->offer_price,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'is_active' => $isActive,
        ]);

        $status = $isActive ? 'activa' : 'inactiva';
        return back()->with('success', "Oferta actualizada exitosamente ({$status}).");
    }

    /**
     * Eliminar oferta activa de un producto
     */
    public function destroy(Product $product): RedirectResponse
    {
        $activeOffer = $product->offers()->active()->first();
        
        if (!$activeOffer) {
            return back()->with('error', 'No hay ofertas activas para eliminar.');
        }

        $activeOffer->update(['is_active' => false]);

        return back()->with('success', 'Oferta eliminada exitosamente.');
    }

    /**
     * Activar/desactivar una oferta específica
     */
    public function toggle(ProductOffer $offer): RedirectResponse
    {
        if ($offer->is_active) {
            // Desactivar esta oferta
            $offer->update(['is_active' => false]);
            $message = 'Oferta desactivada.';
        } else {
            // Activar esta oferta y desactivar otras del mismo producto
            $offer->product->offers()->where('id', '!=', $offer->id)->update(['is_active' => false]);
            $offer->update(['is_active' => true]);
            $message = 'Oferta activada.';
        }

        return back()->with('success', $message);
    }

    /**
     * Crear oferta rápida con campos opcionales
     */
    public function quickOffer(Request $request, Product $product): RedirectResponse
    {
        $request->validate([
            'offer_price' => 'required|numeric|min:0.01|lt:' . $product->price,
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'is_active' => 'boolean',
        ], [
            'offer_price.lt' => 'El precio de oferta debe ser menor al precio original.',
            'end_date.after' => 'La fecha de fin debe ser posterior a la fecha de inicio.',
        ]);

        // Determinar si la oferta debe estar activa
        $isActive = $request->boolean('is_active', true);
        
        // Si se está activando una nueva oferta, desactivar ofertas previas activas
        if ($isActive) {
            $product->offers()->where('is_active', true)->update(['is_active' => false]);
        }

        // Crear nueva oferta
        $product->offers()->create([
            'offer_price' => $request->offer_price,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'is_active' => $isActive,
        ]);

        $status = $isActive ? 'activa' : 'inactiva';
        return back()->with('success', "Oferta creada exitosamente ({$status}).");
    }
}
