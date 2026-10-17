#!/usr/bin/env python3
"""Build and independently verify the FieldFlow immutable ZIP manifest.

Contract: fieldflow-phase4-admin-hotspots-manifest-safety-9dh-v1
No WordPress runtime writes, remote calls, credentials or silent key_files removal.
Usage: python3 tools/build-production-manifest-9dh.py --root ./fieldflow --write --zip /path/release.zip
       python3 tools/build-production-manifest-9dh.py --root ./fieldflow --verify
"""
import argparse
import hashlib
import json
import re
import stat
import sys
import zipfile
from pathlib import Path

MANIFEST = 'production-manifest.json'
BUNDLE = re.compile(r'^assets/(fieldflow-(?:offline-runtime|frontend-core|react-front|react-backoffice))-\d+\.\d+\.\d+\.js$')


def version_of(root):
    source = (root / 'fieldflow.php').read_text('utf-8')
    match = re.search(r"define\(\s*['\"]FIELDFLOW_VERSION['\"]\s*,\s*['\"](\d+\.\d+\.\d+)['\"]\s*\)", source)
    if not match:
        raise ValueError('Cannot establish canonical FIELDFLOW_VERSION')
    return match.group(1)


def inventory(root):
    files = {}
    for path in sorted(root.rglob('*')):
        if path.is_symlink():
            raise ValueError(f'Symlink forbidden: {path.relative_to(root)}')
        if not path.is_file():
            continue
        rel = path.relative_to(root).as_posix()
        if rel == MANIFEST:
            continue
        # No backup or editor residue may hide in an immutable plugin release.
        if path.name.endswith(('.bak','.orig','.swp','~')) or '/__pycache__/' in '/' + rel + '/':
            raise ValueError(f'Unexpected temporary file: {rel}')
        h=hashlib.sha256()
        with path.open('rb') as stream:
            for piece in iter(lambda: stream.read(1024*1024), b''):
                h.update(piece)
        files[rel]={'sha256':h.hexdigest(),'bytes':path.stat().st_size}
    return dict(sorted(files.items()))


def digest(files):
    listing=''.join(f'{name}\t{row["sha256"]}\t{row["bytes"]}\n' for name,row in sorted(files.items()))
    return hashlib.sha256(listing.encode()).hexdigest()


def canonical_keys(keys, files, version):
    remapped=[]
    for key in keys:
        if not isinstance(key,str) or key.startswith('/') or '..' in key.split('/'):
            raise ValueError(f'Invalid key_files entry: {key!r}')
        if key not in files:
            match=BUNDLE.fullmatch(key)
            replacement=f'assets/{match.group(1)}-{version}.js' if match else None
            if not replacement or replacement not in files:
                raise ValueError(f'Missing mandatory key_file (no silent deletion): {key}')
            key=replacement
        if key in remapped:
            raise ValueError(f'Duplicate mandatory key_file: {key}')
        remapped.append(key)
    for stem in ('fieldflow-offline-runtime','fieldflow-frontend-core','fieldflow-react-front','fieldflow-react-backoffice'):
        expected=f'assets/{stem}-{version}.js'
        if expected not in remapped:
            if expected not in files:
                raise ValueError(f'Current frontend asset missing: {expected}')
            remapped.append(expected)
    return remapped


def inspect(root,manifest,files,version):
    if manifest.get('version')!=version:
        raise ValueError('Manifest version not the active release')
    if manifest.get('file_count')!=len(files):
        raise ValueError('File count mismatch')
    if manifest.get('files')!=files:
        raise ValueError('Manifest entries, SHA256 or byte lengths differ from disk')
    if manifest.get('aggregate_sha256')!=digest(files):
        raise ValueError('Aggregate SHA256 mismatch')
    keys=manifest.get('key_files')
    if not isinstance(keys,list) or not keys:
        raise ValueError('key_files must be nonempty list')
    if len(keys)!=len(set(keys)) or any(k not in files for k in keys):
        raise ValueError('Missing or duplicate key_files; certify only an identical installed tree')
    for stem in ('fieldflow-offline-runtime','fieldflow-frontend-core','fieldflow-react-front','fieldflow-react-backoffice'):
        if f'assets/{stem}-{version}.js' not in keys:
            raise ValueError('Current bundle omitted from mandatory key_files: '+stem)
    return True


def archive(root,destination,manifest,files):
    destination.parent.mkdir(parents=True,exist_ok=True)
    if destination.is_relative_to(root):
        raise ValueError('ZIP destination must stay outside the plugin root')
    with zipfile.ZipFile(destination,'w',zipfile.ZIP_DEFLATED,compresslevel=6,allowZip64=True) as z:
        for rel in sorted([*files.keys(),MANIFEST]):
            z.write(root/rel,'fieldflow/'+rel)
    with zipfile.ZipFile(destination) as z:
        names=z.namelist()
        wanted={'fieldflow/'+n for n in [*files.keys(),MANIFEST]}
        if len(names)!=len(wanted) or set(names)!=wanted or z.testzip() is not None:
            raise ValueError('ZIP archive entry contract failed')
        for rel,meta in files.items():
            raw=z.read('fieldflow/'+rel)
            if len(raw)!=meta['bytes'] or hashlib.sha256(raw).hexdigest()!=meta['sha256']:
                raise ValueError('ZIP entry hash mismatch: '+rel)
        installed=json.loads(z.read('fieldflow/'+MANIFEST))
        if installed!=manifest:
            raise ValueError('ZIP manifest differs from source manifest')
    with destination.open('rb') as archive_stream:
        return hashlib.file_digest(archive_stream,'sha256').hexdigest()


def main():
    parser=argparse.ArgumentParser()
    parser.add_argument('--root',type=Path,required=True)
    action=parser.add_mutually_exclusive_group(required=True)
    action.add_argument('--write',action='store_true')
    action.add_argument('--verify',action='store_true')
    parser.add_argument('--zip',type=Path)
    args=parser.parse_args()
    root=args.root.resolve()
    version=version_of(root)
    files=inventory(root)
    manifest_path=root/MANIFEST
    manifest=json.loads(manifest_path.read_text('utf-8'))
    if args.write:
        keys=canonical_keys(manifest['key_files'],files,version)
        manifest={'version':version,'file_count':len(files),'aggregate_sha256':digest(files),'key_files':keys,'files':files}
        manifest_path.write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n','utf-8')
    inspect(root,manifest,files,version)
    print(f'MANIFEST 9DH OK | version={version} files={len(files)} key_files={len(manifest["key_files"])} aggregate={manifest["aggregate_sha256"]}')
    if args.zip:
        h=archive(root,args.zip.resolve(),manifest,files)
        print(f'ZIP 9DH OK | sha256={h} path={args.zip}')


if __name__=='__main__':
    try:
        main()
    except Exception as error:
        print(f'MANIFEST 9DH FAIL: {error}',file=sys.stderr)
        raise SystemExit(1)
