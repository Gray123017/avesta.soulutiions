'use strict';
// Generate an exact-file uninstaller instead of recursively deleting the selected directory.
const fs=require('node:fs');const path=require('node:path');
module.exports=async context=>{
  if(context.electronPlatformName!=='win32')return;
  const files=[],dirs=[];
  function walk(dir,rel=''){for(const entry of fs.readdirSync(dir,{withFileTypes:true})){if(entry.name.startsWith('.'))continue;const name=path.posix.join(rel,entry.name);if(entry.isDirectory()){dirs.push(name);walk(path.join(dir,entry.name),name);}else files.push(name);}}
  walk(context.appOutDir);
  const nsis=s=>s.replaceAll('$','$$').replaceAll('"','$\\"').replaceAll('/','\\');
  const lines=['; Generated from the packaged file inventory.'];
  for(const file of files)lines.push('Delete "$INSTDIR\\'+nsis(file)+'"');
  for(const dir of dirs.sort((a,b)=>b.length-a.length))lines.push('RMDir "$INSTDIR\\'+nsis(dir)+'"');
  lines.push('Delete "$INSTDIR\\Uninstall Subscription Desk.exe"','RMDir "$INSTDIR"');
  fs.writeFileSync(path.join(context.packager.projectDir,'assets','uninstall-files.nsh'),lines.join('\n')+'\n');
};
