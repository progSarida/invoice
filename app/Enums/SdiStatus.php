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
    CASE RIFIUTO_NOTA = "rifiuto_nota";
    CASE RIFIUTO_ARCHIVIATO = "rifiuto_archiviato";
    CASE SCARTO_VALIDATO = "scarto_validato";
    CASE MANCATA_CONSEGNA_VALIDATA = "mancata_consegna_validata";
    CASE AUTO_INVIATA = "auto_inviata";
    CASE APERTA = "fattura_aperta";

    // casi custom
    CASE PREAVVISO = "preavviso";
    CASE QUADRATURA = "quadratura";

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
            self::RIFIUTO_NOTA => 'RN - Rifiuto validato (emessa nota di credito)',
            self::RIFIUTO_ARCHIVIATO => 'RM - Rifiuto validato (mantenere in contabilità)',
            self::SCARTO_VALIDATO => 'SV - Scarto validato (mantenere in contabilità)',
            self::MANCATA_CONSEGNA_VALIDATA => 'MCV - Mancata consegna validata (mantenere in contabilità)',
            self::AUTO_INVIATA => 'Auto inviata',
            self::APERTA => 'Fattura aperta',
            default => ''
        };
    }

    // Icone stati
    public function getIcon(): string
    {
        return match($this) {
            self::EMPTY => '',
            self::DA_INVIARE => 'fluentui-mail-clock-20-o',
            self::INVIATA => 'fluentui-mail-arrow-forward-20-o',
            self::SCARTATA => 'fluentui-mail-error-20-o',
            self::CONSEGNATA => 'fluentui-mail-checkmark-20-o',
            self::MANCATA_CONSEGNA => 'fluentui-mail-dismiss-20-o',
            self::ACCETTATA => 'fluentui-mail-checkmark-20-o',
            self::RIFIUTATA => 'fluentui-mail-dismiss-20-o',
            self::DECORRENZA_TERMINI => 'ri-time-fill',
            self::AVVENUTA_TRASMISSIONE => '',
            self::METADATA => '',

            self::EMESSA => '',
            self::IN_ELABORAZIONE => '',

            self::GENERATA => 'fluentui-mail-arrow-forward-20-o',
            self::TRASMESSA_SDI => 'fluentui-mail-arrow-forward-20-o',
            self::NON_CONSEGNATA => '',
            self::NON_RECAPITABILE => '',
            self::NEL_CASSETTO => '',
            self::RIELABORATA => '',
            self::IMPORTATA => '',

            // self::RIFIUTO_VALIDATO => 'fluentui-presence-blocked-20-o',
            self::RIFIUTO_EMESSO => 'fluentui-document-dismiss-20-o',
            self::RIFIUTO_NOTA => 'fluentui-mail-checkmark-20-o',
            self::RIFIUTO_ARCHIVIATO => 'fluentui-drawer-dismiss-20-o',
            self::SCARTO_VALIDATO => 'fluentui-drawer-dismiss-20',
            self::MANCATA_CONSEGNA_VALIDATA => '',
            self::AUTO_INVIATA => '',
            self::APERTA => '',
            default => ''
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
            self::RIFIUTO_NOTA => 'RN - Rifiuto validato (emessa nota di credito)',
            self::RIFIUTO_ARCHIVIATO => 'RM - Rifiuto validato (mantenere in contabilità)',
            self::SCARTO_VALIDATO => 'SV - Scarto validato (mantenere in contabilità)',
            self::MANCATA_CONSEGNA_VALIDATA => 'MCV - Mancata consegna validata (mantenere in contabilità)',
            self::AUTO_INVIATA => 'Auto inviata',
            self::APERTA => 'Fattura aperta',
            default => ''
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
            self::TRASMESSA_SDI => 'warning',
            self::NON_CONSEGNATA => 'warning',
            self::NON_RECAPITABILE => 'danger',
            self::NEL_CASSETTO => 'gray',
            self::RIELABORATA => 'gray',
            self::IMPORTATA => 'gray',

            // self::RIFIUTO_VALIDATO => 'gray',
            self::RIFIUTO_EMESSO => 'gray',
            self::RIFIUTO_NOTA => 'gray',
            self::RIFIUTO_ARCHIVIATO => 'gray',
            self::SCARTO_VALIDATO => 'gray',
            self::MANCATA_CONSEGNA_VALIDATA => 'gray',
            self::AUTO_INVIATA => 'gray',
            self::APERTA => 'gray',
            default => ''
        };
    }

    // Blocco modifica stato SDI
    public function lockChange(): bool
    {
        return match($this) {
            self::EMPTY => true,
            self::DA_INVIARE => true,
            self::INVIATA => true,
            self::SCARTATA => false,
            self::CONSEGNATA => true,
            self::MANCATA_CONSEGNA => false,
            self::ACCETTATA => true,
            self::RIFIUTATA => false,
            self::DECORRENZA_TERMINI => true,
            self::AVVENUTA_TRASMISSIONE => true,
            self::METADATA => true,

            self::EMESSA => true,
            self::IN_ELABORAZIONE => true,

            self::GENERATA => true,
            self::TRASMESSA_SDI => true,
            self::NON_CONSEGNATA => false,
            self::NON_RECAPITABILE => false,
            self::NEL_CASSETTO => true,
            self::RIELABORATA => true,
            self::IMPORTATA => true,

            // self::RIFIUTO_VALIDATO => 'gray',
            self::RIFIUTO_EMESSO => true,
            self::RIFIUTO_NOTA => true,
            self::RIFIUTO_ARCHIVIATO => true,
            self::SCARTO_VALIDATO => true,
            self::MANCATA_CONSEGNA_VALIDATA => true,
            self::AUTO_INVIATA => true,
            self::APERTA => true,
            default => true
        };
    }

    // Blocco invio fatture a SDI
    public function lockSend(): bool
    {
        return match($this) {
            self::EMPTY => true,
            self::DA_INVIARE => false,
            self::INVIATA => true,
            self::SCARTATA => false,
            self::CONSEGNATA => true,
            self::MANCATA_CONSEGNA => true,
            self::ACCETTATA => true,
            self::RIFIUTATA => true,
            self::DECORRENZA_TERMINI => true,
            self::AVVENUTA_TRASMISSIONE => true,
            self::METADATA => true,

            self::EMESSA => true,
            self::IN_ELABORAZIONE => true,

            self::GENERATA => true,
            self::TRASMESSA_SDI => true,
            self::NON_CONSEGNATA => true,
            self::NON_RECAPITABILE => true,
            self::NEL_CASSETTO => true,
            self::RIELABORATA => false,
            self::IMPORTATA => true,

            // self::RIFIUTO_VALIDATO => 'gray',
            self::RIFIUTO_EMESSO => true,
            self::RIFIUTO_NOTA => true,
            self::RIFIUTO_ARCHIVIATO => true,
            self::SCARTO_VALIDATO => true,
            self::MANCATA_CONSEGNA_VALIDATA => true,
            self::AUTO_INVIATA => true,
            self::APERTA => true,
            default => true
        };
    }

    // Blocco aggiornamento stato da SDI
    public function lockUpdate(): bool
    {
        return match($this) {
            self::EMPTY => true,
            self::DA_INVIARE => true,
            self::INVIATA => false,
            self::SCARTATA => true,
            self::CONSEGNATA => true,
            self::MANCATA_CONSEGNA => true,
            self::ACCETTATA => true,
            self::RIFIUTATA => true,
            self::DECORRENZA_TERMINI => true,
            self::AVVENUTA_TRASMISSIONE => true,
            self::METADATA => true,

            self::EMESSA => true,
            self::IN_ELABORAZIONE => true,

            self::GENERATA => false,
            self::TRASMESSA_SDI => false,
            self::NON_CONSEGNATA => true,
            self::NON_RECAPITABILE => true,
            self::NEL_CASSETTO => true,
            self::RIELABORATA => false,
            self::IMPORTATA => true,

            // self::RIFIUTO_VALIDATO => true,
            self::RIFIUTO_EMESSO => true,
            self::RIFIUTO_NOTA => true,
            self::RIFIUTO_ARCHIVIATO => true,
            self::SCARTO_VALIDATO => true,
            self::MANCATA_CONSEGNA_VALIDATA => true,
            self::AUTO_INVIATA => true,
            self::APERTA => true,
            default => true
        };
    }

    // Mostra come opzione in gestione rifiuto
    public function showReject(): bool
    {
        return match($this) {
            self::EMPTY => false,
            self::DA_INVIARE => false,
            self::INVIATA => false,
            self::SCARTATA => false,
            self::CONSEGNATA => false,
            self::MANCATA_CONSEGNA => false,
            self::ACCETTATA => false,
            self::RIFIUTATA => false,
            self::DECORRENZA_TERMINI => false,
            self::AVVENUTA_TRASMISSIONE => false,
            self::METADATA => false,

            self::EMESSA => false,
            self::IN_ELABORAZIONE => false,

            self::GENERATA => false,
            self::TRASMESSA_SDI => false,
            self::NON_CONSEGNATA => false,
            self::NON_RECAPITABILE => false,
            self::NEL_CASSETTO => false,
            self::RIELABORATA => false,
            self::IMPORTATA => false,

            // self::RIFIUTO_VALIDATO => false,
            self::RIFIUTO_EMESSO => true,
            self::RIFIUTO_NOTA => false,
            self::RIFIUTO_ARCHIVIATO => true,
            self::SCARTO_VALIDATO => false,
            self::MANCATA_CONSEGNA_VALIDATA => false,
            self::AUTO_INVIATA => false,
            self::APERTA => false,
            default => false
        };
    }

    // Mostra come opzione in gestione scarto
    public function showDiscard(): bool
    {
        return match($this) {
            self::EMPTY => false,
            self::DA_INVIARE => false,
            self::INVIATA => false,
            self::SCARTATA => false,
            self::CONSEGNATA => false,
            self::MANCATA_CONSEGNA => false,
            self::ACCETTATA => false,
            self::RIFIUTATA => false,
            self::DECORRENZA_TERMINI => false,
            self::AVVENUTA_TRASMISSIONE => false,
            self::METADATA => false,

            self::EMESSA => false,
            self::IN_ELABORAZIONE => false,

            self::GENERATA => false,
            self::TRASMESSA_SDI => false,
            self::NON_CONSEGNATA => false,
            self::NON_RECAPITABILE => false,
            self::NEL_CASSETTO => false,
            self::RIELABORATA => false,
            self::IMPORTATA => false,

            // self::RIFIUTO_VALIDATO => false,
            self::RIFIUTO_EMESSO => false,
            self::RIFIUTO_NOTA => false,
            self::RIFIUTO_ARCHIVIATO => false,
            self::SCARTO_VALIDATO => true,
            self::MANCATA_CONSEGNA_VALIDATA => false,
            self::AUTO_INVIATA => false,
            self::APERTA => false,
            default => false
        };
    }

    // Blocco modifica stato SDI
    public function updateStatus(): bool
    {
        return match($this) {
            self::EMPTY => false,
            self::DA_INVIARE => false,
            self::INVIATA => true,
            self::SCARTATA => true,
            self::CONSEGNATA => true,
            self::MANCATA_CONSEGNA => true,
            self::ACCETTATA => true,
            self::RIFIUTATA => true,
            self::DECORRENZA_TERMINI => true,
            self::AVVENUTA_TRASMISSIONE => true,
            self::METADATA => true,

            self::EMESSA => true,
            self::IN_ELABORAZIONE => true,

            self::GENERATA => true,
            self::TRASMESSA_SDI => true,
            self::NON_CONSEGNATA => true,
            self::NON_RECAPITABILE => true,
            self::NEL_CASSETTO => true,
            self::RIELABORATA => true,
            self::IMPORTATA => true,

            // self::RIFIUTO_VALIDATO => 'gray',
            self::RIFIUTO_EMESSO => false,
            self::RIFIUTO_NOTA => false,
            self::RIFIUTO_ARCHIVIATO => false,
            self::SCARTO_VALIDATO => false,
            self::MANCATA_CONSEGNA_VALIDATA => false,
            self::AUTO_INVIATA => false,
            self::APERTA => false,
            default => false
        };
    }

    // Codice in nome file ricevuta SDI
    public function sdiReceiptCode(): string
    {
        return match($this) {
            self::EMPTY => '',
            self::DA_INVIARE => '',
            self::INVIATA => '',
            self::SCARTATA => '_NS_',
            self::CONSEGNATA => '_RC_',
            self::MANCATA_CONSEGNA => '_MC_',
            self::ACCETTATA => '_NE_',
            self::RIFIUTATA => '_NE_',
            self::DECORRENZA_TERMINI => '_DT_',
            self::AVVENUTA_TRASMISSIONE => '',
            self::METADATA => '',

            self::EMESSA => '',
            self::IN_ELABORAZIONE => '',

            self::GENERATA => '',
            self::TRASMESSA_SDI => '',
            self::NON_CONSEGNATA => '_MC_',
            self::NON_RECAPITABILE => '_AT_',
            self::NEL_CASSETTO => '',
            self::RIELABORATA => '',
            self::IMPORTATA => '_NE_',                                              // per test, da riportare a '' in produzione

            // self::RIFIUTO_VALIDATO => 'gray',
            self::RIFIUTO_EMESSO => '',
            self::RIFIUTO_NOTA => '',
            self::RIFIUTO_ARCHIVIATO => '',
            self::SCARTO_VALIDATO => '',
            self::MANCATA_CONSEGNA_VALIDATA => '',
            self::AUTO_INVIATA => '',
            self::APERTA => '',
            default => ''
        };
    }
}
