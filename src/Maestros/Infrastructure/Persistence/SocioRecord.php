<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Persistence;

use App\Persistence\TablaConEsquema;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $codigo_de_socio
 * @property string $razon_social
 * @property string|null $id_de_grupo
 * @property CarbonImmutable $vigente_desde
 * @property bool $activo
 * @property CarbonImmutable|null $dado_de_baja_el
 * @property string|null $origen_esquema
 * @property int|null $origen_evento_id
 */
final class SocioRecord extends Model
{
    use TablaConEsquema;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $primaryKey = 'codigo_de_socio';

    protected $guarded = [];

    protected $casts = [
        'vigente_desde' => 'immutable_datetime',
        'importado_el' => 'immutable_datetime',
        'dado_de_baja_el' => 'immutable_datetime',
        'activo' => 'boolean',
        'origen_evento_id' => 'integer',
    ];

    public function getTable(): string
    {
        return $this->tablaEn('maestros', 'socios');
    }
}
