'use strict';
// Refuse to ship a cached bundle whose runtime differs from the reviewed source.
const fs=require('node:fs'),path=require('node:path'),asar=require('@electron/asar');
module.exports=function verifyPackage(appDirectory){
  const root=path.resolve(__dirname,'..'),archive=path.join(appDirectory,'resources','app.asar');
  const expected=require('../package.json').version;
  if(JSON.parse(asar.extractFile(archive,'package.json')).version!==expected)throw new Error('Stale application bundle: rebuild in a fresh output directory.');
  let checked=0;
  function walk(directory){for(const entry of fs.readdirSync(path.join(root,directory),{withFileTypes:true})){
    // asar resolves entries with path.sep, so Windows needs native separators.
    const relative=path.join(directory,entry.name);
    if(entry.isDirectory())walk(relative);
    else {if(!asar.extractFile(archive,relative).equals(fs.readFileSync(path.join(root,relative))))throw new Error('Packaged source differs: '+relative);checked++;}
  }}
  walk('src');console.log('Verified '+checked+' packaged runtime files against source.');
};
