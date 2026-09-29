<?php

declare(strict_types=1);

use App\Persistence\LotesDeScript;

it('parte en GO y descarta lotes vacios o de solo comentarios', function () {
    // Un lote de solo comentarios mandado a SQL Server es un error de sintaxis
    // vacío; y `go` en minúsculas también es separador para sqlcmd.
    $script = "CREATE A;\nGO\n-- solo un comentario\nGO\n/* bloque */\ngo\n  CREATE B;  \nGO\n";

    expect(LotesDeScript::desdeTexto($script))->toBe(['CREATE A;', 'CREATE B;']);
});
