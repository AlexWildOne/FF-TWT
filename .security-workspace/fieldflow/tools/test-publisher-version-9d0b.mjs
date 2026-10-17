import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { resolve, dirname } from 'node:path';
import vm from 'node:vm';
const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const remote = readFileSync(resolve(root,'azure-release-publisher-6b5/release-publisher-function/src/index.js'),'utf8');
function extract(name){
  const match = remote.match(new RegExp('function '+name+'\\([^)]*\\)\\s*\\{[\\s\\S]*?\\n\\}'));
  if(!match) throw new Error('Missing publisher function '+name);
  return vm.runInNewContext('('+match[0]+')');
}
const version = extract('validateVersion');
const channel = extract('validateChannel');
function assert(cond,msg){if(!cond)throw new Error(msg);}
assert(version('2.2.625')==='2.2.625','candidate must be accepted');
assert(version('2.2.626')==='2.2.626','next candidate must be accepted');
assert(version('2.2.624.1')==='','historical hotfix must be rejected');
assert(version('2.2.624')==='2.2.624','installed predecessor accepted');
assert(version('2.2.625.1')==='','4-part future version rejected');
assert(channel('stable')==='stable'&&channel('beta')==='beta','channel stable/beta');
assert(channel('production')==='','invalid channel rejected');
const localValidator = readFileSync(resolve(root,'src/Support/ReleasePackageValidator.php'),'utf8');
const localUploader = readFileSync(resolve(root,'src/Cloud/ReleasePublisherClient.php'),'utf8');
assert(localValidator.includes('9D0B: verify the publisher'), 'local package validator lacks remote syntax check');
assert(localUploader.includes('9D0B: Azure publisher supports'), 'local uploader lacks remote syntax check');
console.log('PUBLISHER VERSION CONTRACT 9D0B PASS; remote accepts=2.2.625; remote rejects=2.2.624.1');
