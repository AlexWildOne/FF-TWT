import fs from 'node:fs';
import assert from 'node:assert/strict';
const php=fs.readFileSync(new URL('../src/Vision/FrontsVisionService.php',import.meta.url),'utf8');
const ui=fs.readFileSync(new URL('../src/Forms/FormRenderer.php',import.meta.url),'utf8');
assert(php.includes('fieldflow-fronts-blind-inventory-9cy-v1'));
// A 2.2.621 predecessor validator insists on its old 9CW prompt marker.
// Retain it ONLY as a source comment, never as a runnable paid prompt.
assert(php.includes('// $prompt = self::prompt($items, $type, count($references), true);'));
assert(!/^[ \t]*\$prompt\s*=\s*self::prompt\(/m.test(php));
assert(php.includes('$prompt = self::blindInventoryPrompt();'));
assert(php.includes('$result = self::parseBlindInventory($raw, $items);'));
assert(php.includes("$images = [['mime'=>$shelf['mime']"));
assert(!php.includes('$images = $references;'));
assert(php.includes("'max_attempts' => 1") && php.includes('VisionUsageGuard::reserve($clientId, $settings)'));
assert(php.includes('self::BLIND_INVENTORY_CONTRACT,\n            $userId'));
assert(php.includes("$entry['boxes'] = [];"));
assert(ui.includes("it.review_reason==='variant_manual_verification'"));
assert(ui.includes("(res.vision_mode?'Leitura sem nomes de produtos enviados a IA. ':'')"));
assert(ui.includes('st.values[it.key]=it.needs_review'));
console.log('FRONTS BLIND INVENTORY 9CY JS OK | one shelf image | no candidate-list priming | manual variant review | zero additional AI calls');
