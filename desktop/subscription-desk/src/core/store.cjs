'use strict';
const fs=require('node:fs');const path=require('node:path');
function atomicWrite(file,data){fs.mkdirSync(path.dirname(file),{recursive:true,mode:0o700});const temp=file+'.tmp';fs.writeFileSync(temp,data,{mode:0o600});fs.renameSync(temp,file);try{fs.chmodSync(file,0o600);}catch{}}
class Store{
  constructor(dir,safeStorage){this.dir=dir;this.safeStorage=safeStorage;this.tokens=null;this.warning='';this.state={schemaVersion:1,items:[],settings:{zone:Intl.DateTimeFormat().resolvedOptions().timeZone||'UTC',calendarAccess:false,scanOnOpen:false},scans:[],activity:[],client:null};this.read();}
  read(){
    const dataFile=path.join(this.dir,'subscriptions.json');
    if(fs.existsSync(dataFile)){try{const s=JSON.parse(fs.readFileSync(dataFile,'utf8'));if(s.schemaVersion!==1||!Array.isArray(s.items))throw new Error('Unsupported data format');this.state={...this.state,...s,settings:{...this.state.settings,...s.settings}};}catch{this.warning='Your local data file could not be opened. It has been left untouched. Import a backup to a new profile or restore the file before continuing.';this.readOnly=true;}}
    const tokenFile=path.join(this.dir,'google-tokens.enc');
    if(fs.existsSync(tokenFile)&&this.secureStorageAvailable()){try{this.tokens=JSON.parse(this.safeStorage.decryptString(fs.readFileSync(tokenFile)));}catch{this.warning='Saved sign-in could not be unlocked. Reconnect Gmail to continue.';}}
  }
  secureStorageAvailable(){return this.safeStorage.isEncryptionAvailable() && !(process.platform==='linux'&&this.safeStorage.getSelectedStorageBackend()==='basic_text');}
  save(){if(this.readOnly)throw new Error(this.warning);atomicWrite(path.join(this.dir,'subscriptions.json'),JSON.stringify(this.state,null,2));}
  saveTokens(tokens){this.tokens=tokens;const file=path.join(this.dir,'google-tokens.enc');if(!tokens){if(fs.existsSync(file))fs.unlinkSync(file);return;}if(this.secureStorageAvailable())atomicWrite(file,this.safeStorage.encryptString(JSON.stringify(tokens)));else if(fs.existsSync(file))fs.unlinkSync(file);}
  log(kind,detail){this.state.activity.unshift({at:new Date().toISOString(),kind,detail});this.state.activity=this.state.activity.slice(0,300);this.save();}
  snapshot(){const {client,...state}=this.state;return{...structuredClone(state),auth:{connected:!!this.tokens,email:this.tokens?.email||'',configured:!!client,calendarGranted:!!this.tokens?.scope?.includes('/auth/calendar.events'),persistent:this.secureStorageAvailable()},warning:this.warning};}
}
module.exports={Store,atomicWrite};
