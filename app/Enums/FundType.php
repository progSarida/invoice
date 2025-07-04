<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum FundType: string implements HasLabel, HasDescription
{
    case TC01 = "tc01";
    case TC02 = "tc02";
    case TC03 = "tc03";
    case TC04 = "tc04";
    case TC05 = "tc05";
    case TC06 = "tc06";
    case TC07 = "tc07";
    case TC08 = "tc08";
    case TC09 = "tc09";
    case TC10 = "tc10";
    case TC11 = "tc11";
    case TC12 = "tc12";
    case TC13 = "tc13";
    case TC14 = "tc14";
    case TC15 = "tc15";
    case TC16 = "tc16";
    case TC17 = "tc17";
    case TC18 = "tc18";
    case TC19 = "tc19";
    case TC20 = "tc20";
    case TC21 = "tc21";
    case TC22 = "tc22";

    public function getCode(): string
    {
        return match($this) {
            self::TC01 => "TC01",
            self::TC02 => "TC02",
            self::TC03 => "TC03",
            self::TC04 => "TC04",
            self::TC05 => "TC05",
            self::TC06 => "TC06",
            self::TC07 => "TC07",
            self::TC08 => "TC08",
            self::TC09 => "TC09",
            self::TC10 => "TC10",
            self::TC11 => "TC11",
            self::TC12 => "TC12",
            self::TC13 => "TC13",
            self::TC14 => "TC14",
            self::TC15 => "TC15",
            self::TC16 => "TC16",
            self::TC17 => "TC17",
            self::TC18 => "TC18",
            self::TC19 => "TC19",
            self::TC20 => "TC20",
            self::TC21 => "TC21",
            self::TC22 => "TC22"
        };
    }

    public function getDescription(): ?string
    {
        return match($this) {
            self::TC01 => "Cassa nazionale previdenza e assistenza avvocati e procuratori legali",
            self::TC02 => "Cassa previdenza dottori commercialisti (CNPADC)",
            self::TC03 => "Cassa previdenza e assistenza geometri (CIPAG)",
            self::TC04 => "Cassa nazionale previdenza e assistenza ingegneri e architetti liberi professionisti (CNPAIALP)",
            self::TC05 => "Cassa nazionale del notariato",
            self::TC06 => "Cassa nazionale previdenza e assistenza ragionieri e periti commercialisti (CNPR)",
            self::TC07 => "Ente nazionale assistenza agenti e rappresentanti di commercio (ENASARCO)",
            self::TC08 => "Ente nazionale previdenza e assistenza consulenti del lavoro (ENPACL)",
            self::TC09 => "Ente nazionale previdenza e assistenza medici (ENPAM)",
            self::TC10 => "Ente nazionale previdenza e assistenza farmacisti (ENPAF)",
            self::TC11 => "Ente nazionale previdena e assistenza veterinari (ENPAV)",
            self::TC12 => "Ente nazionale previdenza e assistenza impiegati dell'agricoltura (ENPAIA)",
            self::TC13 => "Fondo previdenza impiegati imprese di spedizione e agenzie marittime",
            self::TC14 => "Istituto nazionale previdenza giornalisti italiani (INPGI)",
            self::TC15 => "Opera nazionale assistenza orfani sanitari italiani (ONAOSI)",
            self::TC16 => "Cassa autonoma assistenza integrativi giornalisti italiani (CASAGIT)",
            self::TC17 => "Ente previdenza periti industriali e periti industriali laureati (EPPI)",
            self::TC18 => "Ente previdenza e assistenza pluricategoriale (EPAP)",
            self::TC19 => "Ente nazionale previdenza e assistenza biologi (ENPAB)",
            self::TC20 => "Ente nazionale previdenza e assistenza professione infermieristica (ENPAPI)",
            self::TC21 => "Ente nazionale previdenza e assistenza psicologi (ENPAP)",
            self::TC22 => "INPS"
        };
    }

    public function getLabel(): string
    {
        return $this->getCode() . " - " . $this->getDescription();
    }

    public function getShortDesc(): ?string
    {
        return match($this) {
            self::TC01 => "Cassa nazionale previdenza e assistenza avvocati e procuratori legali",
            self::TC02 => "Cassa previdenza dottori commercialisti",
            self::TC03 => "Cassa previdenza e assistenza geometri",
            self::TC04 => "Cassa nazionale previdenza e assistenza ingegneri e architetti liberi professionisti",
            self::TC05 => "Cassa nazionale del notariato",
            self::TC06 => "Cassa nazionale previdenza e assistenza ragionieri e periti commercialisti",
            self::TC07 => "Ente nazionale assistenza agenti e rappresentanti di commercio",
            self::TC08 => "Ente nazionale previdenza e assistenza consulenti del lavoro",
            self::TC09 => "Ente nazionale previdenza e assistenza medici",
            self::TC10 => "Ente nazionale previdenza e assistenza farmacisti",
            self::TC11 => "Ente nazionale previdena e assistenza veterinari",
            self::TC12 => "Ente nazionale previdenza e assistenza impiegati dell'agricoltura",
            self::TC13 => "Fondo previdenza impiegati imprese di spedizione e agenzie marittime",
            self::TC14 => "Istituto nazionale previdenza giornalisti italiani",
            self::TC15 => "Opera nazionale assistenza orfani sanitari italiani",
            self::TC16 => "Cassa autonoma assistenza integrativi giornalisti italiani",
            self::TC17 => "Ente previdenza periti industriali e periti industriali laureati",
            self::TC18 => "Ente previdenza e assistenza pluricategoriale",
            self::TC19 => "Ente nazionale previdenza e assistenza biologi",
            self::TC20 => "Ente nazionale previdenza e assistenza professione infermieristica",
            self::TC21 => "Ente nazionale previdenza e assistenza psicologi",
            self::TC22 => "INPS"
        };
    }

    public function forPrint(): string
    {
        return $this->getCode() . " (" . $this->getShortDesc() . ")";
    }
}
