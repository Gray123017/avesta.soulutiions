import {readFileSync,writeFileSync,mkdirSync,cpSync,readdirSync,statSync,rmSync} from 'node:fs';
import path from 'node:path';
const assets={};const types={'.html':'text/html; charset=utf-8','.js':'text/javascript; charset=utf-8','.css':'text/css; charset=utf-8','.svg':'image/svg+xml','.json':'application/json','.webp':'image/webp','.png':'image/png'};
function walk(dir){for(const f of readdirSync(dir)){const p=path.join(dir,f);if(statSync(p).isDirectory())walk(p);else{const ext=path.extname(p),binary=['.webp','.png'].includes(ext);assets['/'+path.relative('public',p).replaceAll('\\','/')]={body:readFileSync(p,binary?'base64':'utf8'),type:types[ext]||'application/octet-stream',binary};}}}
walk('public');assets['/domain.js']={body:readFileSync('src/domain.js','utf8'),type:'text/javascript; charset=utf-8'};
rmSync('dist',{recursive:true,force:true});mkdirSync('dist/server',{recursive:true});mkdirSync('dist/.openai',{recursive:true});
let worker=readFileSync('src/worker.js','utf8').replace(/^import .*$/gm,'');
worker=readFileSync('src/assistant.js','utf8')+'\n'+worker;
worker=readFileSync('src/domain.js','utf8').replaceAll('export ','')+'\nconst assets='+JSON.stringify(assets)+';\n'+worker;
writeFileSync('dist/server/index.js',worker);cpSync('.openai/hosting.json','dist/.openai/hosting.json');
cpSync('drizzle','dist/drizzle',{recursive:true});
console.log('Built Avesta Worker with '+Object.keys(assets).length+' assets.');
