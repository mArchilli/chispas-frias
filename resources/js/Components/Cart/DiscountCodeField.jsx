import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import toast from 'react-hot-toast';

/**
 * Input para aplicar/quitar un código de descuento del carrito, compartido
 * entre Cart/Index y Cart/Checkout (mismas rutas cart.discount.apply /
 * cart.discount.remove y mismas props `discountCode` que ambas páginas
 * reciben ya resueltas server-side).
 */
export default function DiscountCodeField({ discountCode, removedReason, reloadOnly = ['discountCode', 'subtotal', 'total'] }) {
    const [discountInput, setDiscountInput] = useState('');
    const [discountError, setDiscountError] = useState('');
    const [applyingDiscount, setApplyingDiscount] = useState(false);
    const [removingDiscount, setRemovingDiscount] = useState(false);

    // Avisar si el backend quitó el código automáticamente (se venció, se
    // agotó, o el carrito bajó del mínimo requerido desde la última vez).
    useEffect(() => {
        if (removedReason) {
            toast.error(removedReason);
        }
    }, [removedReason]);

    const applyDiscountCode = async (e) => {
        e.preventDefault();
        if (!discountInput.trim()) return;

        setApplyingDiscount(true);
        setDiscountError('');

        try {
            const response = await axios.post(route('cart.discount.apply'), { code: discountInput.trim() });
            toast.success(response.data.message || 'Código de descuento aplicado.');
            setDiscountInput('');
            router.reload({ only: reloadOnly });
        } catch (error) {
            const message = error.response?.data?.message || 'No pudimos aplicar el código de descuento.';
            setDiscountError(message);
            toast.error(message);
        } finally {
            setApplyingDiscount(false);
        }
    };

    const removeDiscountCode = async () => {
        setRemovingDiscount(true);

        try {
            const response = await axios.delete(route('cart.discount.remove'));
            toast.success(response.data.message || 'Código de descuento quitado.');
            router.reload({ only: reloadOnly });
        } catch (error) {
            toast.error(error.response?.data?.message || 'No pudimos quitar el código de descuento.');
        } finally {
            setRemovingDiscount(false);
        }
    };

    if (discountCode) {
        return (
            <div className="flex items-center justify-between bg-green-50 border border-green-200 rounded-lg px-3 py-2">
                <div>
                    <p className="text-sm font-semibold text-green-700">
                        Código {discountCode.code} aplicado
                    </p>
                    <p className="text-xs text-green-600">
                        -{Number(discountCode.percentage)}% (−${Number(discountCode.amount).toLocaleString('es-AR')})
                    </p>
                </div>
                <button
                    type="button"
                    onClick={removeDiscountCode}
                    disabled={removingDiscount}
                    className="text-sm font-medium text-red-600 hover:text-red-800 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {removingDiscount ? 'Quitando...' : 'Quitar'}
                </button>
            </div>
        );
    }

    return (
        <form onSubmit={applyDiscountCode} className="space-y-2">
            <label className="block text-sm font-medium text-navy">
                Código de descuento
            </label>
            <div className="flex gap-2">
                <input
                    type="text"
                    value={discountInput}
                    onChange={(e) => {
                        setDiscountInput(e.target.value);
                        if (discountError) setDiscountError('');
                    }}
                    placeholder="Ingresá tu código"
                    disabled={applyingDiscount}
                    className="flex-1 min-w-0 px-3 py-2 border border-navy/20 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gold focus:border-transparent disabled:opacity-50"
                />
                <button
                    type="submit"
                    disabled={applyingDiscount || !discountInput.trim()}
                    className="px-4 py-2 bg-navy text-chalk text-sm font-medium rounded-lg hover:bg-navy/90 disabled:opacity-50 disabled:cursor-not-allowed transition-colors whitespace-nowrap"
                >
                    {applyingDiscount ? 'Aplicando...' : 'Aplicar'}
                </button>
            </div>
            {discountError && (
                <p className="text-sm text-red-600">{discountError}</p>
            )}
        </form>
    );
}
