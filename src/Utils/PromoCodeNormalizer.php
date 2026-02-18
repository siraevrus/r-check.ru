<?php

namespace ReproCRM\Utils;

use PDO;

/**
 * Нормализация промокодов. Тире различает типы:
 * - Без тире: "repro 001", "REPRO001" → REPRO001 (один тип).
 * - С тире: "Repro-001", "REPRO-001" → REPRO-001 (другой тип).
 * Дубликаты проверяются по последним трём цифрам в рамках одного типа.
 */
class PromoCodeNormalizer
{
    /**
     * Нормализует промокод с учётом типа (наличие тире в исходной строке).
     * Если в исходном коде есть тире → результат БУКВЫ-ЦИФРЫ (REPRO-001).
     * Если тире нет (пробелы убираются) → результат БУКВЫЦИФРЫ (REPRO001).
     */
    public static function normalize(string $code): string
    {
        $code = trim($code);
        if ($code === '') {
            return '';
        }

        $hasHyphen = (strpos($code, '-') !== false);
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

        return $hasHyphen
            ? $letterPart . '-' . $digits
            : $letterPart . $digits;
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
     * Проверяет, что нормализованный код с тире (формат БУКВЫ-ЦИФРЫ).
     */
    public static function hasHyphenInNormalized(string $normalizedCode): bool
    {
        return strpos($normalizedCode, '-') !== false;
    }

    /**
     * Находит существующий промокод по последним трём цифрам и тому же типу (с тире или без).
     * REPRO001 и REPRO-001 считаются разными промокодами.
     */
    public static function findDuplicateByDigits(string $code, PDO $pdo): ?string
    {
        $normalized = self::normalize($code);
        $digits = self::extractLastThreeDigits($normalized);
        if ($digits === null) {
            return null;
        }

        $hasHyphen = self::hasHyphenInNormalized($normalized);
        $hyphenFlag = $hasHyphen ? 1 : 0;

        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare(
                "SELECT code FROM promo_codes WHERE SUBSTR(REPLACE(REPLACE(code, '-', ''), ' ', ''), -3) = ? " .
                "AND (INSTR(code, '-') > 0) = ? LIMIT 1"
            );
        } else {
            $stmt = $pdo->prepare(
                "SELECT code FROM promo_codes WHERE SUBSTRING(REPLACE(REPLACE(code, '-', ''), ' ', ''), -3) = ? " .
                "AND (LOCATE('-', code) > 0) = ? LIMIT 1"
            );
        }
        $stmt->execute([$digits, $hyphenFlag]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (string) $row['code'] : null;
    }
}
