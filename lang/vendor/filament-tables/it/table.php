<?php

/*
 * Sovrascrive le sole intestazioni delle righe di riepilogo delle tabelle Filament.
 * Il file viene fuso su quello del pacchetto (vendor/filament/tables/resources/lang/it/table.php),
 * quindi tutte le altre stringhe restano invariate.
 *
 * I testi originali erano 'Questa pagina' e 'Tutti gli :label': quest'ultimo componeva
 * l'articolo con il nome della risorsa, producendo forme sbagliate come 'Tutti gli Fatture'.
 */

return [

    'summary' => [

        'subheadings' => [
            'page' => 'Totale pagina',
            'all' => 'Totale complessivo',
        ],

    ],

];
