<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum PaymentReasonType: string implements HasLabel, HasDescription
{
    case A = "a";
    case B = "b";
    case C = "c";
    case D = "d";
    case E = "e";
    case G = "g";
    case H = "h";
    case I = "i";
    case L = "l";
    case L1 = "l1";
    case M = "m";
    case M1 = "m1";
    case M2 = "m2";
    case N = "n";
    case O = "o";
    case O1 = "o1";
    case P = "p";
    case Q = "q";
    case R = "r";
    case S = "s";
    case T = "t";
    case U = "u";
    case V = "v";
    case V1 = "v1";
    case W = "w";
    case X = "x";
    case Y = "y";
    case Z = "z";
    case ZO = "zo";

    public function getCode(): ?string
    {
        return match($this) {
            self::A => "A",
            self::B => "B",
            self::C => "C",
            self::D => "D",
            self::E => "E",
            self::G => "G",
            self::H => "H",
            self::I => "I",
            self::L => "L",
            self::L1 => "L1",
            self::M => "M",
            self::M1 => "M1",
            self::M2 => "M2",
            self::N => "N",
            self::O => "O",
            self::O1 => "O1",
            self::P => "P",
            self::Q => "Q",
            self::R => "R",
            self::S => "S",
            self::T => "T",
            self::U => "U",
            self::V => "V",
            self::V1 => "V1",
            self::W => "W",
            self::X => "X",
            self::Y => "Y",
            self::Z => "Z",
            self::ZO => "ZO"
        };
    }

    public function getDescription(): ?string
    {
        return match($this) {
            self::A => "Prestazioni di lavoro autonomo rientranti nell'esercizio di arte o professione abituale",
            self::B => "Utilizzazione economica, da parte dell'autoreo dell'inventore, di opere dell'ingeno, di brevetti industriali e di processi, formule o informazioni relativi ad esperienze acquisite in campo industriale, commeciale o scientifico",
            self::C => "Utili derivanti da contratti di associazione in partecipazione e da contratti di cointeressenza, quando l'apporto è costituito esclusivamente dalla prestazione di lavoro",
            self::D => "Utili spettanti ai soci promotori ed ai soci fondatori della società di capitali",
            self::E => "Levata di protesti cambiari da parte dei segretari comunali",
            self::G => "Indennità corrisposte per la cessazione di attività sportiva professionale",
            self::H => "Indennità corrisposte per la cessazione dei rapporti di agenzia delle persone fisiche e delle società di persone con esclusione delle somme maturate entro il 31 dicembre 2003, già imputate per competenza e tassate come reddito d'impresa",
            self::I => "Indennità corrisposte per la cessazione di funzioni notarili",
            self::L => "Redditi derivanti dall'utilizzazione economica di opere dell'ingeno, di brevetti industriali e di processi, formule e informazioni relativi a esperienze acquisite incampo industriale, commerciale o scientifico, che sono percepiti  dagli aventi causa a titolo gratuito (ad es. eredi e legatari dell'autore e inventore)",
            self::L1 => "Redditi derivanti dall'utilizzazione economica di opere dell'ingegno, di brevetti industriali e di processi, formule e informazioni relativi a esperienze acquisite in campo industriale, commerciale o scientifico, che sono percepiti da soggetti che abbiano acquistati a titolo oneroso i diritti alla loro utilizzazione",
            self::M => "Prestazioni di lavoro autonomo non esercitate abitualmente",
            self::M1 => "Redditi derivanti dall'assunzione di obblighi di fare, di non fare o permettere",
            self::M2 => "Prestazioni di lavoro autonomo non esercitate abitualmente per le quali sussiste l'obbligo di iscrizione alla Gestione Separata ENPAPI",
            self::N => "Indennità di trasferta, rimborso forfettario di spese, premi e compensi erogati: - nell'esercizio diretto di attività sportive dilettantistiche - in relazione a rapporti di collaborazione coordinata e continuativa di acarttere amministrativo-gestionale di natura non professionale resi a favore di società e associazioni sportive dilettantistiche e di cori, bande e filodrammatiche da parte del direttore e dei collaboratori tecnici",
            self::O => "Prestazioni di lavoro autonomo non esercitate abitualmente, per le quali non sussiste l'obbligo di iscrizione alla gestione separata (Circ. INPS n. 104/2001)",
            self::O1 => "Redditi derivanti dall'assunzione di obblighi di fare, di non fare o permettere, per le quali non sussiste l'obbligo di iscrizione alla gestione separata (Circ. INPS n. 104/2001)",
            self::P => "Compensi corrisposti a soggetti non residenti privi di stabile organizzazione per l'uso o la concessione in uso di attrezzature industriali, commerciali o scientifiche che si trovano nel territorio dello Stato ovvero a società svizzere o stabili organizzazioni di società svizzere che possiedono i requisiti di cui all'art. 15, comma 2 dell'Accordo tra la Comunità europea e la Confederazione svizzera del 26 ottobre 2004 (pubblicato in G.U.C.E. del 29 dicembre 2004 n. L385/30",
            self::Q => "Provvigioni corrisposte ad agente o rappresentante di commercio monomandatario",
            self::R => "Provvigioni corrisposte ad agente o rappresentante di commercio plurimandatario",
            self::S => "Provvigioni corrisposte a commissario",
            self::T => "Provvigioni corrisposte a mediatore",
            self::U => "Provvigioni corrisposte a procacciatore di affari",
            self::V => "Provvigioni corrisposte a incaricato per le vendite a domicilio, provvigioni corrisposte a incaricato per la vendita porta a porta e per la vendita ambulante di giornali quotidiani e periodici (L. 25 febbraio 1987, n. 67)",
            self::V1 => "Redditi derivanti da attività commerciali non esercitate abitulmente (ad esempio provvigioni corrisposteper prestazioni occasionali ad agente o rappresentante di commercio, mediatore, procacciatore d'affari)",
            self::W => "Corrispettivi erogati nel 2020 per prestazioni relative a contratti d'appalto cui si sono resi applicabili le disposizioni contenute nell'art. 25-ter del D.P.R. n. 600 del 29 settembre 1973",
            self::X => "Canoni corrisposti nel 2004 da società o enti residenti ovvero da stabili organizzazioni di società estere di cui all'art. 26-quater, comma 1, lett. a) e b) del D.P.R. 600 del 29 settembre 1973, a società o stabili organizzazioni di società, situate in altro stato membro dell'Unione Europea in presenza di requisiti di cui al citato art. 26-quater, del D.P.R. 600 del 29 settembre 1973, per i quali è stato effettuato, nell'anno 2006, il rimborso della ritenuta ai sensi dell'art. 4 del D.Lgs, 30 maggio 2005 n. 143",
            self::Y => "Canoni corrisposti dal 1° gennaio 2005 al 26 luglio 2005 da società o enti residenti ovvero da stabili organizzazioni di societàestere di cui all'art. 26-quater, comma 1, latt. a) e b) del D.P.R. n. 600 del 29 settembre 1973, a società o stabili organizzazioni di società, situate in altro stato membro dell'Unione Europea in presenza dei requisiti di cui al citato art. 26-quater, del D.P.R. n. 600 del 29 settembre 1973, per i quali è stato effettuato, nell'anno 2006, il rimborso della ritenuta ai sensi dell'art. 4 del D.Lgs. 30 maggio 2005 n. 143",
            self::Z => "Titolo diverso dai precedenti",
            self::ZO => "Altri compensi non rientranti nelle categorie precedenti"
        };
    }

    public function getLabel(): ?string
    {
        return $this->getCode() . " - " . $this->getDescription();
    }

    public function getWording(): ?string
    {
        return " (decodifica come da modello CU)";
    }

    public function getCausal(): ?string
    {
        return $this->getCode() . $this->getWording();
    }
}
