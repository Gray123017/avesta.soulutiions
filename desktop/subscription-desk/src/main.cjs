'use strict';
const {app,BrowserWindow,ipcMain,shell,dialog,safeStorage}=require('electron');
const fs=require('node:fs');const path=require('node:path');const {pathToFileURL}=require('node:url');const crypto=require('node:crypto');
const {Store}=require('./core/store.cjs');
const {GoogleClient,validateClient,authorize}=require('./core/google.cjs');
const {mergeEvidence,importRegister,validDate}=require('./core/model.cjs');
const {providers,getProvider,isSafeEvidenceUrl}=require('./core/catalog.cjs');
const {ics}=require('./core/reminders.cjs');
const {spreadsheet,reportHTML}=require('./core/exports.cjs');
const testProfile=!app.isPackaged ? process.env.SUBSCRIPTION_DESK_TEST_DATA_DIR : null;
if(testProfile)app.setPath('userData',testProfile);
// A development-only profile avoids Unix singleton sockets in isolated CI containers.
if(!testProfile && !app.requestSingleInstanceLock())app.quit();
let win,store,google,scanController,authBusy=false;
const rendererFile=path.join(__dirname,'renderer','index.html');
const rendererURL=pathToFileURL(rendererFile).href;
function getRow(id){const row=store.state.items.find(x=>x.id===id);if(!row)throw new Error('Subscription not found.');return row;}
function state(){return{...store.snapshot(),providers,scanning:!!scanController,version:app.getVersion()};}
function emit(){if(win&&!win.isDestroyed())win.webContents.send('state-changed',state());}
function createWindow(){
  win=new BrowserWindow({width:1400,height:920,minWidth:820,minHeight:640,title:'Subscription Desk',backgroundColor:'#f5f7f2',icon:path.join(__dirname,'../assets/icon.png'),autoHideMenuBar:true,webPreferences:{preload:path.join(__dirname,'preload.cjs'),nodeIntegration:false,contextIsolation:true,sandbox:true,webSecurity:true}});
  win.webContents.setWindowOpenHandler(()=>({action:'deny'}));
  win.webContents.on('will-navigate',(e,url)=>{if(url!==rendererURL)e.preventDefault();});
  win.webContents.session.setPermissionRequestHandler((_wc,_permission,callback)=>callback(false));
  win.loadFile(rendererFile);
  win.webContents.once('did-finish-load',()=>{if(store.state.settings.scanOnOpen&&store.tokens)runScan('13m').catch(()=>{});});
}
async function runScan(range){
  if(scanController)throw new Error('A scan is already running.');
  if(!store.tokens)throw new Error('Connect Gmail to scan your subscription emails.');
  scanController=new AbortController();emit();
  try{
    const knownIds=new Set(store.state.items.filter(r=>r.account===store.tokens.email).flatMap(r=>(r.events||[]).map(e=>e.id)));
    const result=await google.scan({range,signal:scanController.signal,knownIds,onProgress:p=>{if(!win.isDestroyed())win.webContents.send('scan-progress',p);}});
    store.state.items=mergeEvidence(store.state.items,result.events);store.state.scans.unshift(result.scan);store.state.scans=store.state.scans.slice(0,30);
    store.log('Scan',`${result.scan.partial?'Partial scan':'Scan finished'}: ${result.scan.read} messages read, ${result.scan.matched} subscription candidates, ${result.scan.errors.length} errors.`);
    return result.scan;
  }finally{scanController=null;emit();}
}
function makeDemo(){
  const day=n=>{const d=new Date();d.setDate(d.getDate()+n);return d.toISOString().slice(0,10);};
  const rows=[['render','Cloud hosting','payment_failed','Payment failed','Cloud','$18.00','Essential',null],['claude','AI writing plan','paid','Payment received','AI','$20.00','Often',day(16)],['intercom','Support trial','trial_expired','Trial expired','Business','Free trial','Unused',null],['lovable','App builder','renewal_notice','Renewal notice','AI','$25.00','Sometimes',day(24)],['spotify','Music plan','renewed','Renewal confirmed by email','Entertainment','$10.99','Often',day(12)],['google-one','Cloud storage','unknown','Needs review','Storage','Unknown','Unknown',null]];
  return rows.map(([providerKey,name,statusKind,status,category,amount,usage,dueDate],i)=>({id:'demo-'+i,name,providerKey,statusKind,status,category,amount,currency:'USD',cycle:'Monthly',usage,dueDate,dateConfidence:dueDate?'explicit':'unknown',eventKind:'renewal',dateEvidence:dueDate?'Synthetic sample: renews on '+dueDate:'',account:'demo@example.invalid',scope:'Sample account',decision:'Review',reviewed:!!dueDate,identityReview:false,source:'demo',liveStatus:'Not checked',notes:'Demo record only. Replace these examples by connecting Gmail or importing your own register.',events:[{id:'demo-mail-'+i,date:new Date().toISOString(),subject:'Example subscription notice',from:'Sample provider',excerpt:'This is synthetic demonstration data, not a real email or account status.',status,kind:statusKind}]}));
}
async function chooseSave(extension){const r=await dialog.showSaveDialog(win,{defaultPath:'Subscription-Desk-'+new Date().toISOString().slice(0,10)+'.'+extension,filters:[{name:extension.toUpperCase()+' file',extensions:[extension]}]});return r.canceled?null:r.filePath;}
const actions={
  state:()=>state(),
  async importOAuth(){const r=await dialog.showOpenDialog(win,{properties:['openFile'],filters:[{name:'Google Desktop client JSON',extensions:['json']}]});if(r.canceled)return{cancelled:true};if(fs.statSync(r.filePaths[0]).size>100000)throw new Error('Choose the small Google OAuth client JSON file.');store.state.client=validateClient(JSON.parse(fs.readFileSync(r.filePaths[0],'utf8')));store.saveTokens(null);store.log('Setup','Google Desktop OAuth client configured.');return state();},
  async connect(){if(authBusy)throw new Error('Finish the current Google sign-in first.');if(scanController)throw new Error('Wait for the scan to finish before changing accounts.');authBusy=true;try{const tokens=await authorize(store.state.client,url=>shell.openExternal(url),store.state.settings.calendarAccess,store.tokens?.email||'');store.saveTokens(tokens);store.log('Connected','Gmail connected: '+tokens.email);return state();}finally{authBusy=false;}},
  async disconnect(){if(scanController)throw new Error('Stop the scan before disconnecting.');await google.revoke();store.saveTokens(null);store.log('Disconnected','Google access revoked; local records retained.');return state();},
  async settings(p){if(typeof p.zone!=='string'||p.zone.length>100)throw new Error('Enter a valid timezone.');new Intl.DateTimeFormat('en',{timeZone:p.zone}).format(new Date());store.state.settings={zone:p.zone,calendarAccess:!!p.calendarAccess,scanOnOpen:!!p.scanOnOpen};store.save();return state();},
  scan:p=>runScan(p?.range==='all'?'all':'13m'),
  stopScan:()=>{scanController?.abort();return{stopping:true};},
  async import(){const r=await dialog.showOpenDialog(win,{properties:['openFile'],filters:[{name:'Subscription register or backup',extensions:['json']}]});if(r.canceled)return{cancelled:true};if(fs.statSync(r.filePaths[0]).size>20*1024*1024)throw new Error('The register must be smaller than 20 MB.');const rows=importRegister(JSON.parse(fs.readFileSync(r.filePaths[0],'utf8')));let count=0;for(const row of rows){if(!store.state.items.some(x=>x.id===row.id)){store.state.items.push(row);count++;}}store.log('Import',count+' records imported; existing record IDs preserved.');return{...state(),imported:count};},
  demo(){if(!store.state.items.some(x=>x.source==='demo'))store.state.items.push(...makeDemo());store.save();return state();},
  clearDemo(){store.state.items=store.state.items.filter(x=>x.source!=='demo');store.save();return state();},
  add(){const row={id:crypto.randomUUID(),name:'New subscription',account:store.tokens?.email||'Manual record',scope:'Account / project not specified',providerKey:'',category:'Other',source:'manual',status:'Needs review',statusKind:'unknown',amount:'',currency:'Unspecified',cycle:'Unknown',dueDate:null,dateConfidence:'unknown',eventKind:'renewal',decision:'Review',usage:'Unknown',reviewed:false,liveStatus:'Not checked',notes:'',events:[]};store.state.items.push(row);store.save();return{...state(),newId:row.id};},
  edit(p){
    const original=getRow(p.id),row=structuredClone(original);const fields=['name','scope','amount','notes'];for(const k of fields)if(typeof p[k]==='string')row[k]=p[k].trim().slice(0,k==='notes'?10000:500);
    if(!row.name)throw new Error('Enter a service name.');
    for(const [key,values] of Object.entries({decision:['Review','Keep','Cancel'],usage:['Unknown','Essential','Often','Sometimes','Unused'],cycle:['Unknown','Monthly','Annual','Usage based','One-time'],liveStatus:['Not checked','Active — checked by me','Paused — checked by me','Cancelled — checked by me','Free — checked by me','Trial expired — checked by me'],eventKind:['renewal','payment_due','trial_end','access_end','project_deadline'],category:['AI','Cloud','Business','Storage','Entertainment','Creative','Other']}))if(values.includes(p[key]))row[key]=p[key];
    if(p.providerKey===''||getProvider(p.providerKey))row.providerKey=p.providerKey;
    row.reviewed=!!p.reviewed;
    if(p.dueDate!==undefined){if(p.dueDate&&!validDate(p.dueDate))throw new Error('Enter a valid date.');if(p.confirmDate&&p.dueDate){row.dueDate=p.dueDate;row.dateConfidence='user-confirmed';row.dateEvidence='Confirmed by user on '+new Date().toISOString().slice(0,10);row.manualDate={dueDate:row.dueDate,dateConfidence:row.dateConfidence,eventKind:row.eventKind,dateEvidence:row.dateEvidence};}else if(p.dueDate!==row.dueDate){row.dueDate=p.dueDate||null;row.dateConfidence=p.dueDate?'estimated':'unknown';row.manualDate=null;}}
    if(row.manualDate)row.manualDate.eventKind=row.eventKind;
    row.editedAt=new Date().toISOString();Object.assign(original,row);store.log('Record updated',row.name);return state();
  },
  async remove(p){const row=getRow(p.id);if(row.calendar)throw new Error('Remove this record’s Calendar reminder first.');const response=await dialog.showMessageBox(win,{type:'question',buttons:['Keep record','Delete local record'],defaultId:0,cancelId:0,message:'Delete '+row.name+' from this app?',detail:'This only removes your local tracker record. It does not cancel the subscription or change provider billing.'});if(response.response!==1)return{cancelled:true};store.state.items=store.state.items.filter(x=>x.id!==row.id);store.log('Record removed',row.name);return state();},
  async provider(p){
    const row=getRow(p.id),provider=getProvider(row.providerKey);if(!provider)throw new Error('Choose the correct provider in this record or open the original receipt.');
    const url=p.action==='help'?provider.help:provider.url;if(!url)throw new Error('Open the original receipt for its paid subscription management link. No direct billing URL is verified for this provider.');
    if(!['manage','pay','renew','cancel','help'].includes(p.action))throw new Error('Unknown action.');
    if(['pay','renew','cancel'].includes(p.action)){
      const intent={pay:'Review a payment',renew:'Review renewal',cancel:'Review cancellation'}[p.action];
      const result=await dialog.showMessageBox(win,{type:'question',title:intent,buttons:['Go back','Open provider website'],defaultId:0,cancelId:0,message:intent+' for '+row.name+'?',detail:'Account / project: '+row.scope+'\n\n'+provider.cancel+'\n\nYou will finish and confirm the action on '+new URL(url).hostname+'. Opening this page does not change billing or account status.'});
      if(result.response!==1)return{cancelled:true};
    }
    await shell.openExternal(url);store.log('Provider page opened',row.name+' — '+p.action+'; completion not verified.');return{opened:true};
  },
  async evidence(p){const row=getRow(p.id);const e=row.events?.find(x=>x.id===p.eventId);if(!isSafeEvidenceUrl(e?.sourceUrl))throw new Error('No verified Gmail source link is available for this record.');await shell.openExternal(e.sourceUrl);return{opened:true};},
  async setupLink(p){const links={console:'https://console.cloud.google.com/apis/credentials',guide:'https://developers.google.com/identity/protocols/oauth2/native-app',permissions:'https://myaccount.google.com/permissions',calendar:'https://calendar.google.com/calendar/u/0/r'};if(!links[p.key])throw new Error('Unsupported link.');await shell.openExternal(links[p.key]);return{opened:true};},
  async reminder(p){const row=getRow(p.id);if(row.source==='demo')throw new Error('Calendar reminders are disabled for demo data.');row.calendar=await google.createReminder(row,store.state.settings.zone);store.log('Reminder created',row.name+' on '+row.calendar.alertDate+' (five days before '+row.dueDate+').');return state();},
  async removeReminder(p){const row=getRow(p.id);await google.deleteReminder(row);delete row.calendar;store.log('Reminder removed',row.name);return state();},
  async export(p){const format=p.format;if(!['json','xlsx','pdf','ics'].includes(format))throw new Error('Choose a supported export format.');
    let content;if(format==='ics'){const rows=p.id?[getRow(p.id)]:store.state.items.filter(x=>x.reviewed&&x.dueDate&&['explicit','user-confirmed'].includes(x.dateConfidence)&&x.source!=='demo'&&!x.calendar&&x.importedReminder?.due_date!==x.dueDate);if(!rows.length)throw new Error('No reviewed, confirmed dates without existing reminders are available to export.');if(rows.some(x=>x.source==='demo'))throw new Error('Reminder exports are disabled for demo records.');if(rows.some(x=>x.importedReminder?.due_date===x.dueDate))throw new Error('This imported deadline already has a reminder. Review it in Google Calendar first.');content=ics(rows,store.state.settings.zone);}
    const file=await chooseSave(format);if(!file)return{cancelled:true};
    if(format==='json')fs.writeFileSync(file,JSON.stringify({schemaVersion:1,exportedAt:new Date().toISOString(),items:store.state.items,scans:store.state.scans},null,2),{mode:0o600});
    if(format==='ics')fs.writeFileSync(file,content,{mode:0o600});
    if(format==='xlsx')await spreadsheet(store.state,file);
    if(format==='pdf'){const pdf=new BrowserWindow({show:false,webPreferences:{sandbox:true,nodeIntegration:false,contextIsolation:true}});try{await pdf.loadURL('data:text/html;charset=utf-8,'+encodeURIComponent(reportHTML(store.state)));const data=await pdf.webContents.printToPDF({printBackground:true,pageSize:'A4',preferCSSPageSize:true});fs.writeFileSync(file,data,{mode:0o600});}finally{pdf.destroy();}}
    store.log('Export',format.toUpperCase()+' exported. Exports may contain private account information.');return{saved:true,file};
  }
};
app.whenReady().then(()=>{
  store=new Store(app.getPath('userData'),safeStorage);google=new GoogleClient(()=>store.state.client,()=>store.tokens,t=>store.saveTokens(t));
  ipcMain.handle('desk',async(event,action,payload)=>{try{if(event.senderFrame!==win.webContents.mainFrame||event.senderFrame.url!==rendererURL)throw new Error('Untrusted application frame.');if(typeof action!=='string'||!Object.hasOwn(actions,action))throw new Error('Unsupported action.');if(JSON.stringify(payload||{}).length>50000)throw new Error('Request too large.');return{ok:true,data:await actions[action](payload||{})};}catch(error){return{ok:false,error:error.message||'The action could not be completed.'};}});
  createWindow();
  app.on('second-instance',()=>{if(win){if(win.isMinimized())win.restore();win.focus();}});
  app.on('activate',()=>{if(BrowserWindow.getAllWindows().length===0)createWindow();});
});
app.on('window-all-closed',()=>{scanController?.abort();app.quit();});
