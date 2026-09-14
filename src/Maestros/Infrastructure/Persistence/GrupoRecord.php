<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Persistence;

use App\Persistence\TablaConEsquema;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id_de_grupo
 * @property string $nombre
 * @property CarbonImmutable $vigente_desde
 */
final class GrupoRecord extends Model
{
    use TablaConEsquema;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $primaryKey = 'id_de_grupo';

    protected $guarded = [];

    protected $casts = [
        'vigente_desde' => 'immutable_datetime',
        'importado_el' => 'immutable_datetime',
    ];

    public function getTable(): string
    {
        return $this->tablaEn('maestros', 'grupos');
    }
}
