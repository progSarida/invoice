<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum SdiStatus: string implements HasColor, HasLabel, HasDescription, HasIcon
{
    //
    CASE EMPTY = "";
    CASE DA_INVIARE = "da_inviare";
    CASE INVIATA = "inviata";
    CASE SCARTATA = "scartata";
    CASE CONSEGNATA = "consegnata";
    CASE MANCATA_CONSEGNA = "mancata_consegna";
    CASE ACCETTATA = "accettata";
    CASE RIFIUTATA = "rifiutata";
    CASE DECORRENZA_TERMINI = "decorrenza_termini";
    CASE AVVENUTA_TRASMISSIONE = "avvenuta_trasmissione";
    CASE METADATA = "metadata";

    //casi di Agyo
    CASE EMESSA = "emessa";
    CASE IN_ELABORAZIONE = "in_elaborazione";

    //casi ANDXOR
    CASE GENERATA = "generata";
    CASE TRASMESSA_SDI = "trasmessa_sdi";
    CASE NON_CONSEGNATA = "non_consegnata";
    CASE NON_RECAPITABILE = "non_recapitabile";
    CASE NEL_CASSETTO = "nel_cassetto";
    CASE RIELABORATA = "rielaborata";
    CASE IMPORTATA = "importata";

    //AGGIUNTI DA RICCARDO, NON SONO STATUS UFFICIALI DEL SISTEMA DI INTERSCAMBIO
    // CASE RIFIUTO_VALIDATO = "rifiuto_validato";
    CASE RIFIUTO_EMESSO = "rifiuto_emesso";
    CASE RIFIUTO_ARCHIVIATO = "rifiuto_archiviato";
    CASE SCARTO_VALIDATO = "scarto_validato";
    CASE AUTO_INVIATA = "auto_inviata";
    CASE APERTA = "fattura_aperta";

    public function getLabel(): string
    {
        return match($this) {
            self::EMPTY => '',
            self::DA_INVIARE => 'Da inviare',
            self::INVIATA => 'Inviata',
            self::SCARTATA => 'NS - Notifica di scarto',
            self::CONSEGNATA => 'RC - Ricevuta di consegna',
            self::MANCATA_CONSEGNA => 'MC - Mancata consegna',
            self::ACCETTATA => 'NE EC01 - Accettazione',
            self::RIFIUTATA => 'NE EC02 - Rifiuto',
            self::DECORRENZA_TERMINI => 'DT - Decorrenza termini',
            self::AVVENUTA_TRASMISSIONE => 'AT - Impossibilità di recapito',
            self::METADATA => 'MT -Metadati',

            self::EMESSA => 'AGYO - Fattura emessa',
            self::IN_ELABORAZIONE => 'AGYO - In elaborazione',

            self::GENERATA => 'Generata',
            self::TRASMESSA_SDI => 'Trasmessa allo SdI',
            self::NON_CONSEGNATA => 'Non ancora consegnata',
            self::NON_RECAPITABILE => 'Non recapitabile',
            self::NEL_CASSETTO => 'Nel cassetto',
            self::RIELABORATA => 'Rielaborata',
            self::IMPORTATA => 'Importata',

            // self::RIFIUTO_VALIDATO => 'Rifiuto validato',
            self::RIFIUTO_EMESSO => 'RN - Rifiuto validato (emettere nota di credito)',
            self::RIFIUTO_ARCHIVIATO => 'RM - Rifiuto validato (mantenere in contabilità)',
            self::SCARTO_VALIDATO => 'SV - Scarto validato (mantenere in contabilità)',
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

            self::GENERATA => '',
            self::TRASMESSA_SDI => '',
            self::NON_CONSEGNATA => '',
            self::NON_RECAPITABILE => '',
            self::NEL_CASSETTO => '',
            self::RIELABORATA => '',
            self::IMPORTATA => '',

            // self::RIFIUTO_VALIDATO => 'gmdi-block',
            self::RIFIUTO_EMESSO => '',
            self::RIFIUTO_ARCHIVIATO => '',
            self::SCARTO_VALIDATO => '',
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
            self::IN_ELABORAZIONE => 'AGYO - In elaborazione',

            self::GENERATA => 'ANDXOR - Generata',
            self::TRASMESSA_SDI => 'ANDXOR - Trasmessa allo SdI',
            self::NON_CONSEGNATA => 'ANDXOR - Non ancora consegnata',
            self::NON_RECAPITABILE => 'ANDXOR - Non recapitabile',
            self::NEL_CASSETTO => 'ANDXOR - Nel cassetto',
            self::RIELABORATA => 'ANDXOR - Rielaborata',
            self::IMPORTATA => 'ANDXOR - Importata',

            // self::RIFIUTO_VALIDATO => 'Rifiuto validato',
            self::RIFIUTO_EMESSO => 'RN - Rifiuto validato (emettere nota di credito)',
            self::RIFIUTO_ARCHIVIATO => 'RM - Rifiuto validato (mantenere in contabilità)',
            self::SCARTO_VALIDATO => 'SV - Scarto validato (mantenere in contabilità)',
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

            self::GENERATA => 'gray',
            self::TRASMESSA_SDI => 'info',
            self::NON_CONSEGNATA => 'warning',
            self::NON_RECAPITABILE => 'danger',
            self::NEL_CASSETTO => 'gray',
            self::RIELABORATA => 'gray',
            self::IMPORTATA => 'gray',

            // self::RIFIUTO_VALIDATO => 'gray',
            self::RIFIUTO_EMESSO => 'gray',
            self::RIFIUTO_ARCHIVIATO => 'gray',
            self::SCARTO_VALIDATO => 'gray',
            self::AUTO_INVIATA => 'gray',
            self::APERTA => 'gray'
        };
    }
}
