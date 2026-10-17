'use strict';
const {spawnSync,execFileSync}=require('node:child_process');const fs=require('node:fs');const path=require('node:path');
const root=path.resolve(__dirname,'..');
function build(args){const r=spawnSync(process.execPath,[require.resolve('electron-builder/cli.js'),...args],{cwd:root,stdio:'inherit'});if(r.error)throw r.error;if(r.status!==0)throw new Error('Build failed: '+r.status);}
try{
  fs.mkdirSync(path.join(root,'dist'),{recursive:true});
  let source;
  if(!process.argv.includes('--package-only')){
    const fresh=fs.mkdtempSync(path.join(root,'dist','linux-build-'));
    build(['--linux','--x64','--dir','--config.directories.output='+fresh]);
    source=path.join(fresh,'linux-unpacked');
  }else{
    const sourceOption=process.argv.find(arg=>arg.startsWith('--source-dir='));
    source=sourceOption?path.resolve(sourceOption.slice('--source-dir='.length)):path.join(root,'dist','linux-unpacked');
  }
  require('./verify-package.cjs')(source);
  // Some mounted build filesystems expose transient dot-file copies during atomic writes.
  // Package a clean staging tree and never ship those partial copies.
  const stage=fs.mkdtempSync(path.join(root,'dist','linux-release-'));
  fs.cpSync(source,stage,{recursive:true,filter:file=>file===source||(!path.basename(file).startsWith('.')&&file!==path.join(source,'electron'))});
  // A unique output also avoids stale filesystem caches: ar can append to an old .deb.
  const output=fs.mkdtempSync(path.join(root,'dist','linux-package-'));
  const filename='Subscription-Desk-'+require('../package.json').version+'-linux-amd64.deb';
  build(['--linux','deb','--x64','--prepackaged',stage,'--config.directories.output='+output]);
  const built=path.join(output,filename);
  const members=execFileSync('ar',['t',built],{encoding:'utf8'}).trim().split('\n');
  if(members.join(',')!=='debian-binary,control.tar.gz,data.tar.gz')throw new Error('Unexpected Debian archive structure; package not accepted.');
  const destination=path.join(root,'dist','linux-package');fs.mkdirSync(destination,{recursive:true});
  fs.copyFileSync(built,path.join(destination,filename));
  console.log('Linux amd64 .deb created from the clean staging tree.');
}catch(e){console.error(e);process.exitCode=1;}
