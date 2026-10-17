<?php
namespace RoutesPro\Mapping;

use RoutesPro\Stores\StoreIdentityAuthority;

if (!defined('ABSPATH')) exit;

class MappingEngine {
    public static function normalize(string $value): string {
        $value = wp_strip_all_tags($value);
        if (function_exists('remove_accents')) $value = remove_accents($value);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value);
        return trim(preg_replace('/\s+/', ' ', (string)$value));
    }

    public static function compact(string $value, string $entityType = 'store'): string {
        $value = self::normalize($value);
        $noise = $entityType === 'product'
            ? ['garrafa','bottle','unidade','unidades','pack','caixa','cx','produto']
            : ['loja','store','pdv','supermercado','hipermercado','centro','pingo doce','continente','modelo','bom dia','auchan','intermarche'];
        foreach ($noise as $token) $value = preg_replace('/\b' . preg_quote($token, '/') . '\b/u', ' ', $value);
        return trim(preg_replace('/\s+/', ' ', (string)$value));
    }

    public static function storeCandidates(array $record, array $locations, int $limit = 5): array {
        $source = self::normalize((string)($record['source_label'] ?? ''));
        $sourceCompact = self::compact($source, 'store');
        $sourceRef = self::normalize((string)($record['external_ref'] ?? ''));
        $meta = (array)($record['meta_json'] ?? []);
        $sourceLocality = self::normalize((string)($meta['locality'] ?? ''));
        $insignia = (string)($meta['insignia'] ?? $meta['retailer_key'] ?? '');
        $wantedBrand = class_exists('\RoutesPro\Stocks\StockService') ? \RoutesPro\Stocks\StockService::brandFamily($insignia) : '';
        $ranked = [];
        foreach ($locations as $location) {
            $name = self::normalize((string)($location['name'] ?? ''));
            $compact = self::compact($name, 'store');
            $ref = self::normalize((string)($location['external_ref'] ?? ''));
            $city = self::normalize((string)($location['city'] ?? $location['county'] ?? ''));
            $candidateBrand = class_exists('\RoutesPro\Stocks\StockService') ? \RoutesPro\Stocks\StockService::brandFamily((string)($location['name'] ?? '') . ' ' . (string)($location['tags'] ?? '')) : '';
            // 9DN: even a matching external reference cannot bypass retailer identity.
            if (!StoreIdentityAuthority::sourceMatchesLocation($insignia, (string)($record['source_label'] ?? ''), $location, true)) continue;
            $score = 0; $signals = [];
            if ($sourceRef !== '' && $ref !== '' && $sourceRef === $ref) { $score = 100; $signals[] = 'referência exata'; }
            if ($source !== '' && $source === $name) { $score = max($score, 97); $signals[] = 'nome exato'; }
            if ($sourceCompact !== '' && $sourceCompact === $compact) { $score = max($score, 93); $signals[] = 'nome comercial equivalente'; }
            $similarity = self::similarity($sourceCompact ?: $source, $compact ?: $name);
            $overlap = self::tokenOverlap($sourceCompact ?: $source, $compact ?: $name);
            $score = max($score, (int)round(($similarity * .55) + ($overlap * .35)));
            if ($similarity >= 80) $signals[] = 'nome muito semelhante';
            if ($overlap >= 65) $signals[] = 'palavras coincidentes';
            if ($sourceLocality !== '' && $city !== '' && (strpos($city, $sourceLocality) !== false || strpos($sourceLocality, $city) !== false)) { $score += 8; $signals[] = 'localidade coincidente'; }
            if ($wantedBrand !== '' && $candidateBrand === $wantedBrand) { $score += 5; $signals[] = 'insígnia compatível'; }
            $score = max(0, min(100, $score));
            if ($score < 35) continue;
            $ranked[] = [
                'target_id'=>(int)($location['id'] ?? 0),
                'label'=>(string)($location['name'] ?? ''),
                'secondary'=>trim((string)($location['external_ref'] ?? '') . ' ' . (string)($location['city'] ?? '')),
                'confidence'=>$score,
                'signals'=>array_values(array_unique($signals)),
            ];
        }
        usort($ranked, static function($a,$b){$cmp=((int)$b['confidence'])<=>((int)$a['confidence']);return $cmp?:strnatcasecmp((string)$a['label'],(string)$b['label']);});
        return array_slice($ranked,0,max(1,$limit));
    }

    public static function productCandidates(array $record, array $items, int $limit = 5): array {
        $source = self::normalize((string)($record['source_label'] ?? ''));
        $sourceCompact = self::compact($source, 'product');
        $sourceRef = self::normalize((string)($record['external_ref'] ?? ''));
        $meta = (array)($record['meta_json'] ?? []);
        $sourceEan = preg_replace('/\D+/', '', (string)($meta['ean'] ?? ''));
        $ranked=[];
        foreach($items as $item){
            $name=self::normalize((string)($item['product_name']??''));
            $compact=self::compact($name,'product');
            $ref=self::normalize((string)($item['reference']??''));
            $itemMeta=json_decode((string)($item['meta_json']??''),true);if(!is_array($itemMeta))$itemMeta=[];
            $ean=preg_replace('/\D+/','',(string)($itemMeta['ean']??''));
            $score=0;$signals=[];
            if($sourceEan!==''&&$ean!==''&&$sourceEan===$ean){$score=100;$signals[]='EAN exato';}
            if($sourceRef!==''&&$ref!==''&&$sourceRef===$ref){$score=max($score,99);$signals[]='referência exata';}
            if($source!==''&&$source===$name){$score=max($score,96);$signals[]='produto exato';}
            if($sourceCompact!==''&&$sourceCompact===$compact){$score=max($score,92);$signals[]='designação equivalente';}
            $similarity=self::similarity($sourceCompact?:$source,$compact?:$name);$overlap=self::tokenOverlap($sourceCompact?:$source,$compact?:$name);
            $score=max($score,(int)round(($similarity*.6)+($overlap*.35)));
            if($similarity>=82)$signals[]='designação muito semelhante';if($overlap>=65)$signals[]='palavras coincidentes';
            $score=max(0,min(100,$score));if($score<38)continue;
            $ranked[]=['target_id'=>(int)($item['id']??0),'label'=>(string)($item['product_name']??''),'secondary'=>trim((string)($item['reference']??'').' · '.(string)($item['cardex_name']??'')),'confidence'=>$score,'signals'=>array_values(array_unique($signals))];
        }
        usort($ranked,static function($a,$b){$cmp=((int)$b['confidence'])<=>((int)$a['confidence']);return $cmp?:strnatcasecmp((string)$a['label'],(string)$b['label']);});
        return array_slice($ranked,0,max(1,$limit));
    }

    public static function confidenceLabel(int $confidence): string {
        if ($confidence >= 95) return 'Muito alta';
        if ($confidence >= 85) return 'Alta';
        if ($confidence >= 70) return 'Média';
        if ($confidence >= 50) return 'Baixa';
        return 'Muito baixa';
    }

    private static function similarity(string $left,string $right): int {
        if($left===''||$right==='')return 0;if($left===$right)return 100;similar_text($left,$right,$percent);return (int)round($percent);
    }
    private static function tokenOverlap(string $left,string $right): int {
        $a=array_values(array_unique(array_filter(explode(' ',$left))));$b=array_values(array_unique(array_filter(explode(' ',$right))));if(!$a||!$b)return 0;$intersection=count(array_intersect($a,$b));$union=count(array_unique(array_merge($a,$b)));return $union?(int)round(($intersection/$union)*100):0;
    }
}
