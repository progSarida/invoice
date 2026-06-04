<?php
namespace App\Services;

class CurrencyService {
    public static function parseNumber($value): float|null {
        if (is_numeric($value)) return (float) $value;
        if (empty($value)) return null;
        // Rimuove i punti delle migliaia e sostituisce la virgola con il punto
        $clean = str_replace(',', '.', str_replace('.', '', $value));

        return is_numeric($clean) ? (float) $clean : null;
    }
}
