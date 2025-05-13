<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum SdiStatus: string implements HasColor, HasLabel, HasDescription, HasIcon
{
    //
    case EMPTY = "";
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

    public function getLabel(): string
    {
        return match($this) {
            self::EMPTY => '',
            self::DA_INVIARE => 'Da inviare',
            self::INVIATA => 'Inviata',
            self::SCARTATA => 'Scartata',
            self::CONSEGNATA => 'Consegnata',
            self::MANCATA_CONSEGNA => 'Mancata consegna',
            self::ACCETTATA => 'Accettata',
            self::RIFIUTATA => 'Rifiutata',
            self::DECORRENZA_TERMINI => 'Decorrenza termini',
            self::AVVENUTA_TRASMISSIONE => 'Impossibilità di recapito',
            self::METADATA => 'Metadati',

            self::EMESSA => 'AGYO - Fattura emessa',
            self::IN_ELABORAZIONE => 'AGYO - In elaborazione',

            self::RIFIUTO_VALIDATO => 'Rifiuto validato',
            self::AUTO_INVIATA => 'Auto inviata',
            self::APERTA => 'Fattura aperta'
        };
    }

    public function getIcon(): string
    {
        return match($this) {
            self::EMPTY => '',
            self::DA_INVIARE => '',
            self::INVIATA => '',
            self::SCARTATA => 'gmdi-block',
            self::CONSEGNATA => '',
            self::MANCATA_CONSEGNA => '',
            self::ACCETTATA => '',
            self::RIFIUTATA => 'gmdi-block',
            self::DECORRENZA_TERMINI => 'vaadin-time-forward',
            self::AVVENUTA_TRASMISSIONE => '',
            self::METADATA => '',

            self::EMESSA => '',
            self::IN_ELABORAZIONE => '',

            self::RIFIUTO_VALIDATO => 'gmdi-block',
            self::AUTO_INVIATA => '',
            self::APERTA => ''
        };
    }

    public function getDescription(): string
    {
        return match($this) {
            self::EMPTY => '',
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
            self::AUTO_INVIATA => 'Auto inviata',
            self::APERTA => 'Fattura aperta'
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::EMPTY => '',
            self::DA_INVIARE => 'warning',
            self::INVIATA => 'info',
            self::SCARTATA => 'danger',
            self::CONSEGNATA => 'info',
            self::MANCATA_CONSEGNA => 'danger',
            self::ACCETTATA => 'success',
            self::RIFIUTATA => 'danger',
            self::DECORRENZA_TERMINI => 'success',
            self::AVVENUTA_TRASMISSIONE => 'danger',
            self::METADATA => 'info',

            self::EMESSA => 'warning',
            self::IN_ELABORAZIONE => 'warning',

            self::RIFIUTO_VALIDATO => 'gray',
            self::AUTO_INVIATA => 'gray',
            self::APERTA => 'gray'
        };
    }
}
