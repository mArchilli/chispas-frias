<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductOffer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class ProductOfferAdminController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $offers = ProductOffer::with('product')
            ->latest()
            ->paginate(15);

        // Calculate percentage discount for offers that don't have it
        $offers->getCollection()->transform(function ($offer) {
            if (is_null($offer->percentage_discount) && $offer->product->price > 0) {
                $offer->percentage_discount = round((($offer->product->price - $offer->offer_price) / $offer->product->price) * 100, 2);
                $offer->save(); // Save the calculated percentage
            }
            return $offer;
        });

        $products = Product::select('id', 'title')
            ->orderBy('title')
            ->get();

        return Inertia::render('Admin/Offers/Index', [
            'offers' => $offers,
            'products' => $products
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'offer_price' => 'required|numeric|min:0',
            'percentage_discount' => 'nullable|numeric|min:0|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $product = Product::findOrFail($request->product_id);

            // Validate that offer price is less than regular price
            if ($request->offer_price >= $product->price) {
                return back()->withErrors(['offer_price' => 'El precio de oferta debe ser menor al precio regular del producto.']);
            }

            // Calculate percentage discount if not provided
            $percentageDiscount = $request->percentage_discount;
            if (is_null($percentageDiscount)) {
                $percentageDiscount = round((($product->price - $request->offer_price) / $product->price) * 100, 2);
            }

            ProductOffer::create([
                'product_id' => $request->product_id,
                'offer_price' => $request->offer_price,
                'percentage_discount' => $percentageDiscount,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'is_active' => $request->is_active ?? true,
            ]);

            return back()->with('success', 'Oferta creada exitosamente');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al crear la oferta: ' . $e->getMessage()]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ProductOffer $offer)
    {
        $validator = Validator::make($request->all(), [
            'offer_price' => 'required|numeric|min:0',
            'percentage_discount' => 'nullable|numeric|min:0|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            // Validate that offer price is less than regular price
            if ($request->offer_price >= $offer->product->price) {
                return back()->withErrors(['offer_price' => 'El precio de oferta debe ser menor al precio regular del producto.']);
            }

            // Calculate percentage discount if not provided
            $percentageDiscount = $request->percentage_discount;
            if (is_null($percentageDiscount)) {
                $percentageDiscount = round((($offer->product->price - $request->offer_price) / $offer->product->price) * 100, 2);
            }

            $offer->update([
                'offer_price' => $request->offer_price,
                'percentage_discount' => $percentageDiscount,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'is_active' => $request->is_active ?? true,
            ]);

            return back()->with('success', 'Oferta actualizada exitosamente');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al actualizar la oferta: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProductOffer $offer)
    {
        try {
            $offer->delete();
            return back()->with('success', 'Oferta eliminada exitosamente');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al eliminar la oferta: ' . $e->getMessage()]);
        }
    }

    /**
     * Toggle offer status
     */
    public function toggleStatus(ProductOffer $offer)
    {
        try {
            $offer->update(['is_active' => !$offer->is_active]);
            
            $status = $offer->is_active ? 'activada' : 'desactivada';
            return back()->with('success', "Oferta {$status} exitosamente");
            
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al cambiar estado de la oferta: ' . $e->getMessage()]);
        }
    }
}