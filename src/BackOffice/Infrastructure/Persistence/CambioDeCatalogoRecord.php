<?php

declare(strict_types=1);

namespace BackOffice\Infrastructure\Persistence;

use App\Persistence\TablaConEsquema;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id_de_cambio
 * @property string $id_de_operador
 * @property string $operador
 * @property int $id_producto
 * @property string|null $item_code
 * @property string $campo
 * @property string|null $valor_anterior
 * @property string|null $valor_nuevo
 * @property CarbonImmutable $ocurrio_el
 * @property string $direccion_ip
 */
final class CambioDeCatalogoRecord extends Model
{
    use TablaConEsquema;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $primaryKey = 'id_de_cambio';

    protected $guarded = [];

    protected $casts = [
        'id_producto' => 'integer',
        'ocurrio_el' => 'immutable_datetime',
    ];

    public function getTable(): string
    {
        return $this->tablaEn('backoffice', 'cambios_de_catalogo');
    }
}
