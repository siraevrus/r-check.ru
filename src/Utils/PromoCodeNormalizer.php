<?php

namespace ReproCRM\Utils;

use PDO;

/**
 * Нормализация промокодов к формату БУКВЫ-ЦИФРЫ (например, REPRO-001).
 * Проверка дубликатов по последним трем цифрам.
 */
class PromoCodeNormalizer
{
    /**
     * Нормализует промокод к формату БУКВЫ-ЦИФРЫ (например, REPRO-001).
     * Удаляет дефисы и пробелы, извлекает последние 3 цифры и буквенную часть.
     */
    public static function normalize(string $code): string
    {
        $code = trim($code);
        if ($code === '') {
            return '';
        }

        $clean = preg_replace('/[\s\-]+/u', '', $code);
        if ($clean === '') {
            return '';
        }

        $digits = self::extractLastThreeDigits($clean);
        if ($digits === null) {
            return strtoupper($code);
        }

        $letterPart = preg_replace('/\d+$/u', '', $clean);
        $letterPart = preg_replace('/[^a-zA-Zа-яА-ЯёЁ]/u', '', $letterPart);
        $letterPart = strtoupper($letterPart);

        if ($letterPart === '') {
            return strtoupper($code);
        }

        return $letterPart . '-' . $digits;
    }

    /**
     * Извлекает последние 3 цифры из строки промокода.
     * Если цифр меньше 3, дополняет ведущими нулями.
     * Возвращает null, если цифр нет.
     */
    public static function extractLastThreeDigits(string $code): ?string
    {
        $clean = preg_replace('/[\s\-]+/u', '', $code);
        if (!preg_match('/(\d{1,3})$/u', $clean, $m)) {
            return null;
        }
        return str_pad($m[1], 3, '0', STR_PAD_LEFT);
    }

    /**
     * Находит существующий промокод в базе по последним трем цифрам.
     * Возвращает нормализованный код (code) или null.
     */
    public static function findDuplicateByDigits(string $code, PDO $pdo): ?string
    {
        $digits = self::extractLastThreeDigits($code);
        if ($digits === null) {
            return null;
        }

        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare(
                "SELECT code FROM promo_codes WHERE SUBSTR(REPLACE(REPLACE(code, '-', ''), ' ', ''), -3) = ? LIMIT 1"
            );
        } else {
            $stmt = $pdo->prepare(
                "SELECT code FROM promo_codes WHERE SUBSTRING(REPLACE(REPLACE(code, '-', ''), ' ', ''), -3) = ? LIMIT 1"
            );
        }
        $stmt->execute([$digits]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (string) $row['code'] : null;
    }
}
