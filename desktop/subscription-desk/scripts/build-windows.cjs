'use strict';
const {spawnSync}=require('node:child_process');const path=require('node:path');const fs=require('node:fs');
const {getMakeNsisPath}=require('app-builder-lib/out/toolsets/windows');
async function main(){
  const root=path.resolve(__dirname,'..'),pkg=require('../package.json');
  const run=(exe,args,options={})=>{const r=spawnSync(exe,args,{cwd:root,stdio:'inherit',...options});if(r.error)throw r.error;if(r.status!==0)throw new Error('Build command failed with exit '+r.status);};
  if(!process.argv.includes('--installer-only'))run(process.execPath,[require.resolve('electron-builder/cli.js'),'--win','--x64','--dir']);
  require('./verify-package.cjs')(path.join(root,'dist','win-unpacked'));
  const nsis=await getMakeNsisPath();
  const out=path.join(root,'dist',`Subscription-Desk-${pkg.version}-win-x64.exe`);
  // Pass complete host-native paths; Windows NSIS wildcard lookup rejects mixed separators.
  const defines=['-DLICENSE_FILE='+path.join(root,'LICENSE'),'-DPACKAGED_FILES='+path.join(root,'dist','win-unpacked','*.*'),'-DUNINSTALL_FILE_LIST='+path.join(root,'assets','uninstall-files.nsh')];
  run(nsis.path,['-V2','-INPUTCHARSET','UTF8',...defines,'-DVERSION='+pkg.version,'-DMUI_ICON='+path.join(root,'assets','icon.ico'),'-DMUI_UNICON='+path.join(root,'assets','icon.ico'),'-XUnicode true','-XOutFile "'+out+'"','-XVIProductVersion '+pkg.version+'.0','-XVIAddVersionKey /LANG=1033 ProductName "Subscription Desk"','-XVIAddVersionKey /LANG=1033 FileDescription "Subscription Desk x64 installer"','-XVIAddVersionKey /LANG=1033 FileVersion "'+pkg.version+'"','-XVIAddVersionKey /LANG=1033 LegalCopyright "Subscription Desk contributors"',path.join(root,'assets','installer.nsi')],{env:{...process.env,...nsis.env}});
  if(fs.statSync(out).size<10000000)throw new Error('Installer is unexpectedly small; build not accepted.');
  console.log('Windows x64 installer created: '+out);
}
main().catch(e=>{console.error(e);process.exitCode=1;});
