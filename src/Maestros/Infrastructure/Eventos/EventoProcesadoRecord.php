<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Eventos;

use App\Persistence\TablaConEsquema;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $evento_id
 * @property string $tipo
 * @property string $resultado
 * @property string $procesado_en
 */
final class EventoProcesadoRecord extends Model
{
    use TablaConEsquema;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $primaryKey = 'evento_id';

    protected $guarded = [];

    public function getTable(): string
    {
        return $this->tablaEn('maestros', 'evento_procesado');
    }
}
