<?php

namespace App\Enums;

enum RolUsuario: string
{
    case Admin = 'admin';
    case Vendedor = 'vendedor';
    case Cliente = 'cliente';
}
