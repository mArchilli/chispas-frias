<?php

namespace Tests\Support;

use Closure;
use Rukoz\CoreClient\Facades\Rukoz;

/**
 * Doble de prueba del cliente del paquete (RukozClient es `final`, no se puede mockear con Mockery).
 * Se instala con `RukozFalso::instalar()`, que lo deja detrás de la fachada `Rukoz`, y registra las
 * llamadas en orden: [['sincronizarProductos', [15]], ['sincronizarCombos', [3]], ['syncCatalogo', [payload]], ...].
 */
class RukozFalso
{
    /** @var list<array{0: string, 1: array}> */
    public array $llamadas = [];

    public ?Closure $alLlamar = null;

    public static function instalar(): self
    {
        $falso = new self;
        Rukoz::swap($falso);

        return $falso;
    }

    public function habilitada(): bool
    {
        return true;
    }

    public function sincronizarProductos(array $ids): array
    {
        return $this->registrar('sincronizarProductos', $ids);
    }

    public function sincronizarCombos(array $ids): array
    {
        return $this->registrar('sincronizarCombos', $ids);
    }

    /** `POST /catalogo/sync` directo (lo usa la web para avisar lo que ya no está en la base). */
    public function syncCatalogo(array $payload, ?string $refLocal = null): array
    {
        return $this->registrar('syncCatalogo', [$payload]);
    }

    /** @return list<array> los argumentos de cada llamada al método indicado */
    public function idsDe(string $metodo): array
    {
        return array_values(array_map(fn ($l) => $l[1], array_filter($this->llamadas, fn ($l) => $l[0] === $metodo)));
    }

    private function registrar(string $metodo, array $ids): array
    {
        $this->llamadas[] = [$metodo, $ids];

        if ($this->alLlamar !== null) {
            ($this->alLlamar)($metodo, $ids);
        }

        return [];
    }
}
