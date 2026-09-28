import { Dialog, DialogPanel } from '@headlessui/react';

export default function Modal({
    children,
    show = false,
    maxWidth = '2xl',
    closeable = true,
    onClose = () => {},
}) {
    const close = () => {
        if (closeable) {
            onClose();
        }
    };

    const maxWidthClass = {
        sm: 'sm:max-w-sm',
        md: 'sm:max-w-md',
        lg: 'sm:max-w-lg',
        xl: 'sm:max-w-xl',
        '2xl': 'sm:max-w-2xl',
    }[maxWidth];

    return (
        <Dialog
            as="div"
            id="modal"
            open={show}
            className="fixed inset-0 z-50 flex items-center overflow-y-auto px-4 py-6 sm:px-0"
            onClose={close}
        >
            <div className="fixed inset-0 bg-navy-900/75" aria-hidden="true" />
            <DialogPanel className={`relative mb-6 overflow-hidden rounded-lg bg-surface shadow-xl sm:mx-auto sm:w-full ${maxWidthClass}`}>
                {children}
            </DialogPanel>
        </Dialog>
    );
}
