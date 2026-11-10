<?php

namespace App\Support\ComputerRental;

use Illuminate\Http\Request;

/** Solicitud creada solo por el servicio de renta, nunca por HTTP. */
final class ComputerRentalInternalPosRequest extends Request
{
}
