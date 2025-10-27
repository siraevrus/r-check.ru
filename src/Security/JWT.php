<?php

namespace ReproCRM\Security;

use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

class JWT
{
    private static string $secret;
    private static string $algorithm = 'HS256';
    
    public static function init(): void
    {
        self::$secret = $_ENV['JWT_SECRET'] ?? 'default_secret_change_in_production';
    }
    
    public static function generate(array $payload, int $expirationHours = 24): string
    {
        $payload['iat'] = time();
        $payload['exp'] = time() + ($expirationHours * 3600);
        
        return FirebaseJWT::encode($payload, self::$secret, self::$algorithm);
    }
    
    public static function validate(string $token): ?array
    {
        try {
            $decoded = FirebaseJWT::decode($token, new Key(self::$secret, self::$algorithm));
            return (array) $decoded;
        } catch (ExpiredException $e) {
            return null;
        } catch (SignatureInvalidException $e) {
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    public static function getTokenFromHeader(): ?string
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
}
