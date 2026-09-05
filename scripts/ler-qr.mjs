import { readFileSync } from 'node:fs';
import { PNG } from 'pngjs';
import jsQR from 'jsqr';

const ficheiro = process.argv[2];
const png = PNG.sync.read(readFileSync(ficheiro));
const r = jsQR(new Uint8ClampedArray(png.data), png.width, png.height);
console.log(r ? 'LIDO: ' + r.data : 'ILEGIVEL');
