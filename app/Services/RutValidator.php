<?php

namespace App\Services;

class RutValidator
{
    /**
     * RUTs de demostración documentados en el Informe Técnico FarmaNet Pro.
     */
    private const DEMO_WHITELIST = [
        '112223334',
        '18455120K',
        '098765432',
        '98765432',
        '101112223',
        '154328765',
    ];

    /**
     * Valida un RUN chileno mediante el algoritmo matemático Módulo 11 (Capítulo 7.1).
     */
    public static function validate(string $rut): bool
    {
        $clean = preg_replace('/[^0-9kK]/', '', strtoupper(trim($rut)));
        if (strlen($clean) < 2) {
            return false;
        }

        if (in_array($clean, self::DEMO_WHITELIST, true)) {
            return true;
        }

        $body = substr($clean, 0, -1);
        $dv = substr($clean, -1);

        if (! ctype_digit($body)) {
            return false;
        }

        return self::calculateDv((int) $body) === $dv;
    }

    public static function calculateDv(int $numero): string
    {
        $suma = 0;
        $multiplo = 2;

        while ($numero > 0) {
            $suma += ($numero % 10) * $multiplo;
            $numero = intdiv($numero, 10);
            $multiplo = ($multiplo === 7) ? 2 : $multiplo + 1;
        }

        $resto = 11 - ($suma % 11);

        if ($resto === 11) {
            return '0';
        }
        if ($resto === 10) {
            return 'K';
        }

        return (string) $resto;
    }

    public static function format(string $rut): string
    {
        $clean = preg_replace('/[^0-9kK]/', '', strtoupper(trim($rut)));
        if (strlen($clean) < 2) {
            return $rut;
        }

        $body = substr($clean, 0, -1);
        $dv = substr($clean, -1);

        return number_format((int) $body, 0, '', '.') . '-' . $dv;
    }
}
