<?php

namespace App\Enums;

enum SdiStatus: string
{
    //

    case DA_INVIARE = "da_inviare";
    case INVIATA = "inviata";
    case SCARTATA = "scartata";
    case CONSEGNATA = "consegnata";
    case MANCATA_CONSEGNA = "mancata_consegna";
    case ACCETTATA = "accettata";
    case RIFIUTATA = "rifiutata";
    CASE DECORRENZA_TERMINI = "decorrenza_termini";
    CASE AVVENUTA_TRASMISSIONE = "avvenuta_trasmissione";
    CASE METADATA = "metadata";

    //casi di Agyo
    CASE EMESSA = "emessa";
    CASE IN_ELABORAZIONE = "in_elaborazione";

    //AGGIUNTI DA RICCARDO, NON SONO STATUS UFFICIALI DEL SISTEMA DI INTERSCAMBIO
    CASE RIFIUTO_VALIDATO = "rifiuto_validato";
    CASE AUTO_INVIATA = "auto_inviata";
    CASE APERTA = "fattura_aperta";

    public function label(): string
    {
        return match($this) {
            self::DA_INVIARE => 'Da inviare',
            self::INVIATA => 'Inviata',
            self::SCARTATA => 'NS - Notifica di scarto',
            self::CONSEGNATA => 'RC - Ricevuta di consegna',
            self::MANCATA_CONSEGNA => 'MC - Mancata consegna',
            self::ACCETTATA => 'NE EC01 - Notifica esito accettazione',
            self::RIFIUTATA => 'NE EC02 - Notifica esito rifiuto',
            self::DECORRENZA_TERMINI => 'DT - Decorrenza termini',
            self::AVVENUTA_TRASMISSIONE => 'AT - Avvenuta trasmissione con impossibilità di recapito',
            self::METADATA => 'MT - Metadati',

            self::EMESSA => 'AGYO - Fattura emessa',
            self::IN_ELABORAZIONE => 'Agyo - In elaborazione',

            self::RIFIUTO_VALIDATO => 'Rifiuto validato',
            self::AUTO_INVIATA => 'Rifiuto validato',
            self::APERTA => 'Fattura aperta'
        };
    }
}
