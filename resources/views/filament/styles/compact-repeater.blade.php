<style>
    /*
        Repeater usato come sola lista riordinabile: le voci portano solo campi nascosti
        e l'etichetta è nell'intestazione, quindi il corpo della voce va rimosso perché
        resterebbe un riquadro vuoto alto quanto la spaziatura interna.
    */
    .fi-fo-repeater.compact-repeater .fi-fo-repeater-item-content {
        display: none;
    }

    .fi-fo-repeater.compact-repeater ul {
        gap: 0.375rem;
    }
</style>
