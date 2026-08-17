<?php

namespace App\Support;

/**
 * Fuente única de las provincias soportadas por el checkout público (con sus
 * localidades sugeridas). CartController::checkout() la usa para armar el
 * selector de provincia/ciudad; OrderController la usa para traducir el slug
 * guardado en orders.province a un nombre legible en las métricas de admin.
 */
class Provincias
{
    public static function all(): array
    {
        return [
            'buenos-aires' => [
                'name' => 'Buenos Aires',
                'cities' => [
                    'La Plata', 'Mar del Plata', 'Bahía Blanca', 'Tandil', 'Olavarría',
                    'Junín', 'Pergamino', 'Necochea', 'San Nicolás', 'Azul', 'Quilmes',
                    'San Isidro', 'Vicente López', 'San Martín', 'Morón', 'Avellaneda',
                    'Lanús', 'Lomas de Zamora', 'Almirante Brown', 'Esteban Echeverría',
                ],
            ],
            'caba' => [
                'name' => 'Ciudad Autónoma de Buenos Aires',
                'cities' => [
                    'Palermo', 'Recoleta', 'San Telmo', 'Puerto Madero', 'Belgrano',
                    'Villa Crespo', 'Caballito', 'Flores', 'Villa Urquiza', 'Núñez',
                ],
            ],
            'cordoba' => [
                'name' => 'Córdoba',
                'cities' => [
                    'Córdoba', 'Río Cuarto', 'Villa María', 'San Francisco', 'Villa Carlos Paz',
                    'Alta Gracia', 'Bell Ville', 'Marcos Juárez', 'Jesús María', 'La Falda',
                ],
            ],
            'santa-fe' => [
                'name' => 'Santa Fe',
                'cities' => [
                    'Rosario', 'Santa Fe', 'Rafaela', 'Reconquista', 'Venado Tuerto',
                    'Esperanza', 'Santo Tomé', 'Casilda', 'Firmat', 'Villa Gobernador Gálvez',
                ],
            ],
            'mendoza' => [
                'name' => 'Mendoza',
                'cities' => [
                    'Mendoza', 'San Rafael', 'Godoy Cruz', 'Las Heras', 'Maipú',
                    'Rivadavia', 'San Martín', 'Tupungato', 'Malargüe', 'General Alvear',
                ],
            ],
            'tucuman' => [
                'name' => 'Tucumán',
                'cities' => [
                    'San Miguel de Tucumán', 'Tafí Viejo', 'Yerba Buena', 'Banda del Río Salí',
                    'Concepción', 'Aguilares', 'Bella Vista', 'Monteros', 'Famaillá', 'Lules',
                ],
            ],
            'salta' => [
                'name' => 'Salta',
                'cities' => [
                    'Salta', 'San Ramón de la Nueva Orán', 'Tartagal', 'General Güemes',
                    'Metán', 'Cafayate', 'Rosario de Lerma', 'Campo Quijano', 'El Carmen', 'Cerrillos',
                ],
            ],
            'entre-rios' => [
                'name' => 'Entre Ríos',
                'cities' => [
                    'Paraná', 'Concordia', 'Gualeguaychú', 'Concepción del Uruguay',
                    'Victoria', 'Villaguay', 'Crespo', 'Chajarí', 'Colón', 'Federal',
                ],
            ],
        ];
    }

    /**
     * Nombre legible para un slug de provincia (ej. "buenos-aires" -> "Buenos Aires").
     * Fallback a un título armado a partir del slug si no está en el catálogo,
     * para no romper con datos viejos/manuales.
     */
    public static function nombre(?string $slug): ?string
    {
        if (!$slug) {
            return null;
        }

        return self::all()[$slug]['name'] ?? ucwords(str_replace('-', ' ', $slug));
    }
}
