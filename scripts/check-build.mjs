import {readFileSync, readdirSync, existsSync} from 'node:fs';
import {execFileSync} from 'node:child_process';
for(const name of readdirSync('assets/avesta')) {
 const path='assets/avesta/'+name;
 if(name.endsWith('.js'))execFileSync(process.execPath,['--check',path]);
 if(name.endsWith('.json'))JSON.parse(readFileSync(path,'utf8'));
}
for(const path of ['index.php','apply.php','auth.php','loans.php','it.php','login.php','legal.php','images/how-it-works.webp','icons/icon-192.png'])if(!existsSync(path))throw new Error('Missing '+path);
if(/\/api\//.test(readFileSync('assets/avesta/app.js','utf8')))throw new Error('Cloudflare API dependency in PHP frontend');
console.log('Hostinger assets validated; deploy repository files directly. No server build required.');
