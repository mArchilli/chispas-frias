/**
 * Obtiene la URL completa de una imagen de producto
 * @param {string} imagePath - La ruta de la imagen almacenada
 * @returns {string} - URL completa de la imagen
 */
export const getProductImageUrl = (imagePath) => {
    if (!imagePath) {
        return null;
    }
    
    // Si la imagen ya tiene el protocolo http/https, devolverla tal como está
    if (imagePath.startsWith('http')) {
        return imagePath;
    }
    
    // Si la imagen ya comienza con /, usar directamente
    if (imagePath.startsWith('/')) {
        return imagePath;
    }
    
    // Usar la variable de entorno VITE para construir la URL
    const basePath = import.meta.env.VITE_PRODUCT_IMAGES_PATH || '/images/products/';
    
    // Construir la URL directa (sin /storage/)
    return `${basePath}${imagePath}`;
};

/**
 * Obtiene la URL de una imagen de producto desde el objeto de imagen
 * @param {Object} image - Objeto de imagen con propiedad path o url
 * @returns {string|null} - URL de la imagen
 */
export const getImageUrl = (image) => {
    if (!image) {
        return null;
    }
    
    // Si tiene URL directa, usarla
    if (image.url) {
        return image.url;
    }
    
    // Si tiene path, construir la URL
    if (image.path) {
        return getProductImageUrl(image.path);
    }
    
    return null;
};

/**
 * Obtiene la URL de la imagen principal de un producto
 * @param {Object} product - Objeto producto con images array
 * @returns {string|null} - URL de la imagen principal
 */
export const getPrimaryImageUrl = (product) => {
    if (!product || !product.images || product.images.length === 0) {
        return null;
    }
    
    // Buscar imagen principal
    const primaryImage = product.images.find(img => img.is_primary);
    if (primaryImage) {
        return getImageUrl(primaryImage);
    }
    
    // Si no hay imagen principal, usar la primera
    return getImageUrl(product.images[0]);
};

/**
 * Obtiene todas las URLs de imágenes de un producto
 * @param {Object} product - Objeto producto con images array
 * @returns {Array} - Array de URLs de imágenes
 */
export const getProductImageUrls = (product) => {
    if (!product || !product.images || product.images.length === 0) {
        return [];
    }
    
    return product.images.map(image => getImageUrl(image)).filter(Boolean);
};