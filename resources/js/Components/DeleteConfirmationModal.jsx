import React from 'react';
import Modal from './Modal';
import DangerButton from './DangerButton';
import SecondaryButton from './SecondaryButton';

function DeleteConfirmationModal({ 
    show, 
    onClose, 
    onConfirm, 
    title = "¿Confirmar eliminación?",
    message = "Esta acción no se puede deshacer.",
    itemName = null,
    warningMessage = null,
    confirmText = "Eliminar",
    processing = false
}) {
    return (
        <Modal show={show} onClose={onClose}>
            <div className="p-6">
                <div className="flex items-center mb-4">
                    <div className="flex-shrink-0 w-10 h-10 mx-auto bg-red-100 rounded-full flex items-center justify-center">
                        <svg className="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                    </div>
                </div>
                
                <h2 className="text-lg font-medium text-gray-900 mb-4 text-center">
                    {title}
                </h2>
                
                <div className="text-sm text-gray-600 mb-6 text-center space-y-2">
                    <p>{message}</p>
                    
                    {itemName && (
                        <p className="font-semibold text-gray-900">"{itemName}"</p>
                    )}
                    
                    {warningMessage && (
                        <p className="text-red-600 font-medium">{warningMessage}</p>
                    )}
                </div>
                
                <div className="flex justify-center space-x-4">
                    <SecondaryButton onClick={onClose} disabled={processing}>
                        Cancelar
                    </SecondaryButton>
                    <DangerButton onClick={onConfirm} disabled={processing}>
                        {processing ? 'Eliminando...' : confirmText}
                    </DangerButton>
                </div>
            </div>
        </Modal>
    );
}

export default DeleteConfirmationModal;