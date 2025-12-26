// Configuración personalizada para React Quill
export const quillModules = {
    toolbar: [
        [{ 'header': [1, 2, 3, false] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
        [{ 'color': [] }, { 'background': [] }],
        ['link'],
        ['clean']
    ],
};

export const quillFormats = [
    'header', 'bold', 'italic', 'underline', 'strike',
    'list', 'bullet', 'color', 'background', 'link'
];

// Configuración para vista previa (solo lectura)
export const quillReadOnlyModules = {
    toolbar: false
};

// Función para limpiar HTML de Quill y obtener solo texto plano
export const stripHtmlTags = (html) => {
    if (!html) return '';
    const doc = new DOMParser().parseFromString(html, 'text/html');
    return doc.body.textContent || '';
};

// Función para obtener un resumen del texto
export const getTextSummary = (html, maxLength = 150) => {
    const plainText = stripHtmlTags(html);
    if (plainText.length <= maxLength) return plainText;
    return plainText.substring(0, maxLength).trim() + '...';
};