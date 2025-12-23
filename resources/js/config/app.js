/**
 * Configuración de la aplicación para el frontend
 */

export const APP_CONFIG = {
    // URLs de imágenes
    PRODUCT_IMAGES_PATH: import.meta.env.VITE_PRODUCT_IMAGES_PATH || '/images/products/',
    
    // Otras configuraciones que podrías necesitar en el futuro
    APP_NAME: import.meta.env.VITE_APP_NAME || 'Chispas Frías',
    
    // URLs base
    API_URL: import.meta.env.VITE_API_URL || '/api',
    STORAGE_URL: '/storage',
};

export default APP_CONFIG;