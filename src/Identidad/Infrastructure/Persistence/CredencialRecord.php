<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Persistence;

use App\Persistence\TablaConEsquema;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id_de_persona
 * @property string $contrasena_cifrada
 */
final class CredencialRecord extends Model
{
    use TablaConEsquema;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $primaryKey = 'id_de_persona';

    protected $guarded = [];

    protected $casts = [
        'rotada_el' => 'immutable_datetime',
    ];

    public function getTable(): string
    {
        return $this->tablaEn('identidad', 'credenciales');
    }
}
