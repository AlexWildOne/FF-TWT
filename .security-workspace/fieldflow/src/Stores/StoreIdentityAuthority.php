<?php
namespace RoutesPro\Stores;

use RoutesPro\Stocks\StockService;

if (!defined('ABSPATH')) exit;

/** Unified 9DN store identity. Numeric refs, locality and historical mappings are never identity evidence. */
final class StoreIdentityAuthority {
    public const CONTRACT = 'fieldflow-universal-store-identity-authority-9dn-v1';

    public static function family(string $value): string {
        $key = strtolower(trim($value));
        if ($key === 'pingo_doce') return 'pingodoce';
        if ($key === 'eci') return 'elcorteingles';
        if ($key === 'itm' || $key === 'itmi') return 'intermarche';
        return StockService::brandFamily($value);
    }

    public static function subtype(string $value): string {
        return self::family($value) === 'continente' ? StockService::sonaeSubtype($value) : '';
    }

    public static function canonicalLabel(string $value): string {
        $family = self::family($value);
        if ($family === 'continente') return match (self::subtype($value)) {
            'mdl' => 'Continente Modelo', 'cbd' => 'Continente Bom Dia', 'cnt' => 'Continente', default => 'Sonae',
        };
        return match ($family) {
            'pingodoce'=>'Pingo Doce', 'arcol'=>'Arcol', 'mcunha'=>'M. Cunha',
            'makro'=>'Makro', 'recheio'=>'Recheio', 'auchan'=>'Auchan',
            'intermarche'=>'Intermarché', 'elcorteingles'=>'El Corte Inglés',
            'minipreco'=>'Minipreço', 'mercadona'=>'Mercadona', 'lidl'=>'Lidl',
            'aldi'=>'Aldi', 'froiz'=>'Froiz', 'apolonia'=>'Apolónia',
            'eclerc'=>'E.Leclerc', default=>'',
        };
    }

    /** The same 9DM verification already used by Stocks, across every store consumer. */
    public static function locationFamily(array $location): string {
        $identified = [];
        foreach (['Sonae','Pingo Doce','Arcol','M. Cunha','Makro','Recheio',
            'Auchan','Intermarché','El Corte Inglés','Minipreço','Mercadona',
            'Lidl','Aldi','Froiz','Apolónia','E.Leclerc'] as $label) {
            if (StockService::locationMatchesInsignia($location, $label)) {
                $identified[self::family($label)] = true;
            }
        }
        return count($identified) === 1 ? (string)array_key_first($identified) : '';
    }

    public static function locationMatches(string $insignia, array $location): bool {
        $family = self::family($insignia);
        if ($family === '' || self::locationFamily($location) !== $family) return false;
        if (!StockService::locationMatchesInsignia($location, self::canonicalLabel($insignia))) return false;
        return $family !== 'continente' || self::subtype($insignia) === ''
            || self::targetSubtype($location) === self::subtype($insignia);
    }

    /** Source subtype must agree with target; generic Sonae briefing aliases remain unlinked. */
    public static function sourceMatchesLocation(string $insignia, string $source, array $location, bool $requireSonaeSubtype = false): bool {
        $family = self::family($insignia);
        if ($family === '' || !self::locationMatches($insignia, $location)) return false;
        $sourceFamily = self::family($source);
        if ($sourceFamily !== '' && $sourceFamily !== $family) return false;
        if (!StockService::sourceMatchesInsignia($source, self::canonicalLabel($insignia))) return false;
        if ($family !== 'continente') return true;
        $sourceSubtype = self::subtype($source);
        $requiredSubtype = self::subtype($insignia);
        $targetSubtype = self::targetSubtype($location);
        if ($requiredSubtype !== '' && $targetSubtype !== $requiredSubtype) return false;
        if ($requireSonaeSubtype && $requiredSubtype === '' && $sourceSubtype === '') return false;
        return $sourceSubtype === '' || ($targetSubtype !== '' && $sourceSubtype === $targetSubtype);
    }

    /** CSV updates must never rebrand a known store or remove its established identity. */
    public static function canUpdateLocation(array $old, array $incoming): bool {
        $before = self::locationFamily($old);
        $after = self::locationFamily($incoming);
        if ($before === '' || $after === '' || $before !== $after) return false;
        if ($before !== 'continente') return true;
        $oldSubtype = self::targetSubtype($old);
        return $oldSubtype !== '' && $oldSubtype === self::targetSubtype($incoming);
    }

    public static function targetSubtype(array $location): string {
        $nameSubtype = self::subtype((string)($location['name'] ?? ''));
        if ($nameSubtype !== '') return $nameSubtype;
        $values = [];
        $tags = (string)($location['tags'] ?? '');
        if ($tags !== '') {
            $decoded = json_decode($tags, true);
            if (is_array($decoded)) {
                foreach ($decoded as $part) if (is_string($part)) $values[] = $part;
            } else {
                $values = array_merge($values, preg_split('/[,;|]+/', $tags) ?: []);
            }
        }
        $meta = $location['meta_json'] ?? [];
        if (is_string($meta)) $meta = json_decode($meta, true);
        if (is_array($meta)) {
            foreach (['insignia','insignia_key','retailer','retailer_key','store_brand','chain','brand'] as $key) {
                if (isset($meta[$key]) && is_string($meta[$key])) $values[] = $meta[$key];
            }
        }
        $found = [];
        foreach ($values as $part) {
            $sub = self::subtype((string)$part);
            if ($sub !== '') $found[$sub] = true;
        }
        return count($found) === 1 ? (string)array_key_first($found) : '';
    }
}
