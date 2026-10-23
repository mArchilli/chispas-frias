<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Handler\FormattableHandlerInterface;

/**
 * Tap de los canales `single` y `daily`: tapa los datos personales que puedan colarse en una línea del log. El código de la
 * aplicación no loguea clientes (notas, teléfonos, DNI, mails), pero una excepción de base de datos (`QueryException`) lleva la
 * consulta con los valores y un error de validación puede nombrar un dato. Ver `FormateadorSinDatosPersonales`.
 */
class FormatoSinDatosPersonales
{
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getLogger()->getHandlers() as $handler) {
            if ($handler instanceof FormattableHandlerInterface) {
                // Mismo formato que arma Laravel por defecto (salto de línea permitido, stack trace incluido).
                $handler->setFormatter(new FormateadorSinDatosPersonales(null, 'Y-m-d H:i:s', true, true, true));
            }
        }
    }
}
