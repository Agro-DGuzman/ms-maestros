<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Persistence;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @property string $id_de_sesion
 * @property string $id_de_persona
 * @property string|null $id_de_instalacion
 * @property string|null $plataforma
 * @property string|null $refresh_token_hash
 * @property CarbonImmutable $iniciada_en
 * @property CarbonImmutable $expira_en
 * @property CarbonImmutable|null $cerrada_en
 */
final class SesionRecord extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $primaryKey = 'id_de_sesion';

    protected $guarded = [];

    protected $casts = [
        'iniciada_en' => 'immutable_datetime',
        'expira_en' => 'immutable_datetime',
        'cerrada_en' => 'immutable_datetime',
    ];

    public function getTable(): string
    {
        return DB::getDriverName() === 'sqlsrv' ? 'identidad.sesiones' : 'identidad_sesiones';
    }
}
