<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Documents PDF Path
    |--------------------------------------------------------------------------
    |
    | Carpeta dentro de public/ donde se guardan y se leen los PDFs de los
    | documentos para vendedores. Debe ser relativa al directorio public.
    | Configurable vía DOCUMENTS_PDF_PATH, mismo patrón que PRODUCT_IMAGES_PATH.
    |
    */

    'pdf_path' => env('DOCUMENTS_PDF_PATH', 'docs/pdfs/'),

];
