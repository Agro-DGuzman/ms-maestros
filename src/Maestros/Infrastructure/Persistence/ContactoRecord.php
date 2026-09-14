<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Persistence;

use App\Persistence\TablaConEsquema;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id_de_persona
 * @property string $codigo_de_socio
 * @property string $nombre
 * @property string $celular
 * @property CarbonImmutable|null $habilitada_el
 * @property CarbonImmutable $vigente_desde
 */
final class ContactoRecord extends Model
{
    use TablaConEsquema;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $primaryKey = 'id_de_persona';

    protected $guarded = [];

    protected $casts = [
        'habilitada_el' => 'immutable_datetime',
        'vigente_desde' => 'immutable_datetime',
        'importado_el' => 'immutable_datetime',
    ];

    public function getTable(): string
    {
        return $this->tablaEn('maestros', 'contactos');
    }
}
