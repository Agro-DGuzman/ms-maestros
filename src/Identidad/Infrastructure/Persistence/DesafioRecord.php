<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Persistence;

use App\Persistence\TablaConEsquema;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id_de_desafio
 * @property string $celular
 * @property string $digitos
 * @property CarbonImmutable $expira_en
 * @property int $intentos_fallidos
 * @property bool $consumido
 */
final class DesafioRecord extends Model
{
    use TablaConEsquema;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $primaryKey = 'id_de_desafio';

    protected $guarded = [];

    protected $casts = [
        'expira_en' => 'immutable_datetime',
        'emitido_el' => 'immutable_datetime',
        'intentos_fallidos' => 'integer',
        'consumido' => 'boolean',
    ];

    public function getTable(): string
    {
        return $this->tablaEn('identidad', 'desafios');
    }
}
