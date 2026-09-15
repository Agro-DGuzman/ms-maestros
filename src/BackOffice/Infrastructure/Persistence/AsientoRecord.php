<?php

declare(strict_types=1);

namespace BackOffice\Infrastructure\Persistence;

use App\Persistence\TablaConEsquema;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id_de_asiento
 * @property string $id_de_operador
 * @property string $operador
 * @property string $id_de_persona
 * @property string $accion
 * @property CarbonImmutable $ocurrio_el
 * @property string $direccion_ip
 */
final class AsientoRecord extends Model
{
    use TablaConEsquema;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $primaryKey = 'id_de_asiento';

    protected $guarded = [];

    protected $casts = [
        'ocurrio_el' => 'immutable_datetime',
    ];

    public function getTable(): string
    {
        return $this->tablaEn('backoffice', 'asientos_de_bitacora');
    }
}
