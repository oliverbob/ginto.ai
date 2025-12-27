# Hosting Monaco Editor Locally

The admin script editor can use the Monaco editor (the same open-source editor that powers VS Code).

By default the editor will load a locally-vendored copy from `/assets/vendor/monaco-editor/min/vs/loader.js`.
This distribution should be vendored into the project — the initialization code will not fall back to a CDN in production for security and air-gapped environments.

## Recommended: vendor a local copy

We provide a small helper script to fetch the Monaco distribution and place the `min/` folder under `public/assets/vendor/monaco-editor`.

Run this from the project root. The installer supports multiple sources:

- Using npm (recommended when available):

```bash
# install specific version into the project's public assets (default 0.39.0)
scripts/install_monaco.sh 0.39.0
```

- From a downloaded tarball (no npm required):

```bash
# pass a local .tgz you previously downloaded
scripts/install_monaco.sh /path/to/monaco-editor-0.39.0.tgz
```

- From an existing local folder (already extracted min/ directory):

```bash
# copy the min/ folder into the vendor target (the script will copy the contents)
scripts/install_monaco.sh /path/to/monaco/min
```

```bash
# by default installs v0.39.0
scripts/install_monaco.sh 0.39.0
```

This will create `public/assets/vendor/monaco-editor/min/vs/loader.js` and other required files.

If you cannot use `npm` the script will try to download the package tarball from the npm registry and extract `min/` — or you can pass a local tarball or directory as shown above.

## Why host locally?

- Works in air-gapped / private deployments
- Avoids runtime dependency on a 3rd party CDN
- Gives you control over the exact bundled version

## Alternative

If you prefer to vendor the files manually, download the `min/` directory from the `monaco-editor` npm package and place it under `public/assets/vendor/monaco-editor/min` (or use the installer with a local path). 

Make sure the file `public/assets/vendor/monaco-editor/min/vs/loader.js` is present. The editor initialization script will then load Monaco from this path.