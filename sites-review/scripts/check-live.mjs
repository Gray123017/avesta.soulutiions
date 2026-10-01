// Read {url,token} once from stdin; never persist or print the service credential.
import readline from 'node:readline';
const line=await new Promise(resolve=>{const rl=readline.createInterface({input:process.stdin});rl.once('line',s=>{rl.close();resolve(s);});});
const {url,token}=JSON.parse(line);const origin=new URL(url).origin;
if(!origin.endsWith('.chatgpt.site')||!origin.startsWith('https://'))throw new Error('Expected the selected Site origin.');
const paths=['/','/lending','/how-it-works','/calculator','/currency','/apply','/it','/about','/contact','/portal','/privacy','/terms','/complaints','/app.js','/assistant.js','/styles.css','/help.json','/api/health','/api/assistant/status','/api/rates'];
const results=[];
for(let i=0;i<paths.length;i+=4)await Promise.all(paths.slice(i,i+4).map(async path=>{
 try{const res=await fetch(origin+path,{redirect:'manual',headers:{'OAI-Sites-Authorization':'Bearer '+token},signal:AbortSignal.timeout(15000)});const result={path,status:res.status};
 if(path==='/api/health'||path==='/api/assistant/status')result.data=await res.json();
 else if(res.ok){const body=await res.text();if(!path.includes('.')&&!path.startsWith('/api/'))result.pageValid=body.includes('/assistant.js')&&body.includes('info@avesta.solutions');}
 results.push(result);
 }catch{results.push({path,status:0,error:'Request unavailable'});}
}));
console.log(JSON.stringify({checkedAt:new Date().toISOString(),results},null,2));
if(results.some(r=>r.status!==200||r.pageValid===false))process.exitCode=1;
