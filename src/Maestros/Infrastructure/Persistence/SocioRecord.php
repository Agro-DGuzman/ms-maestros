<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Persistence;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @property string $codigo_de_socio
 * @property string $razon_social
 * @property string $id_de_grupo
 * @property CarbonImmutable $vigente_desde
 */
final class SocioRecord extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $primaryKey = 'codigo_de_socio';

    protected $guarded = [];

    protected $casts = [
        'vigente_desde' => 'immutable_datetime',
        'importado_el' => 'immutable_datetime',
    ];

    public function getTable(): string
    {
        return DB::getDriverName() === 'sqlsrv' ? 'maestros.socios' : 'maestros_socios';
    }
}
