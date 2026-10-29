<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Eventos;

use App\Persistence\TablaConEsquema;
use Illuminate\Database\Eloquent\Model;

/**
 * Sin casts: los instantes pasan por InstanteUtc, y pdo_sqlsrv devuelve el
 * `uniqueidentifier` en mayúsculas.
 *
 * @property string $evento_id
 * @property string $tipo
 * @property string $sobre
 * @property string $creado_en
 * @property string|null $publicado_en
 * @property int|string $intentos
 */
final class EventoSalienteRecord extends Model
{
    use TablaConEsquema;

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'evento_id';

    protected $keyType = 'string';

    protected $guarded = [];

    public function getTable(): string
    {
        return $this->tablaEn('maestros', 'evento_saliente');
    }
}
