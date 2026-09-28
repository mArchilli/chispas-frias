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
export default function DiscountCodeField({ discountCode, removedReason, reloadOnly = ['discountCode', 'subtotal', 'total'], onChanged, inputId = 'discount-code' }) {
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
            if (onChanged) {
                await onChanged();
            } else {
                router.reload({ only: reloadOnly });
            }
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
            if (onChanged) {
                await onChanged();
            } else {
                router.reload({ only: reloadOnly });
            }
        } catch (error) {
            toast.error(error.response?.data?.message || 'No pudimos quitar el código de descuento.');
        } finally {
            setRemovingDiscount(false);
        }
    };

    if (discountCode) {
        return (
            <div className="flex items-center justify-between gap-3 rounded-2xl border border-navy-700/25 bg-ice-50 px-4 py-3">
                <div>
                    <p className="text-sm font-semibold text-navy-700">
                        Código {discountCode.code} aplicado
                    </p>
                    <p className="text-xs text-navy-700">
                        -{Number(discountCode.percentage)}% (−${Number(discountCode.amount).toLocaleString('es-AR')})
                    </p>
                </div>
                <button
                    type="button"
                    onClick={removeDiscountCode}
                    disabled={removingDiscount}
                    className="min-h-9 rounded-full border border-storefront px-3 text-sm font-semibold text-storefront transition hover:bg-storefront hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {removingDiscount ? 'Quitando...' : 'Quitar'}
                </button>
            </div>
        );
    }

    return (
        <form onSubmit={applyDiscountCode} className="space-y-2">
            <label htmlFor={inputId} className="block text-sm font-semibold text-navy-900">
                Código de descuento
            </label>
            <div className="flex gap-2">
                <input
                    id={inputId}
                    type="text"
                    value={discountInput}
                    onChange={(e) => {
                        setDiscountInput(e.target.value);
                        if (discountError) setDiscountError('');
                    }}
                    placeholder="Ingresá tu código"
                    disabled={applyingDiscount}
                    className="min-w-0 flex-1 rounded-full border border-navy-900/20 bg-white px-4 py-2 text-sm text-navy-900 outline-none transition placeholder:text-navy-900/40 focus:border-navy-700 focus:ring-2 focus:ring-ice-500 disabled:opacity-50"
                />
                <button
                    type="submit"
                    disabled={applyingDiscount || !discountInput.trim()}
                    className="min-h-10 whitespace-nowrap rounded-full bg-storefront px-4 py-2 text-sm font-semibold text-white transition hover:brightness-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-storefront disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-500"
                >
                    {applyingDiscount ? 'Aplicando...' : 'Aplicar'}
                </button>
            </div>
            {discountError && (
                <p role="alert" className="text-sm text-navy-700">{discountError}</p>
            )}
        </form>
    );
}
