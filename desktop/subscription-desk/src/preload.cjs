'use strict';
const {contextBridge,ipcRenderer}=require('electron');
contextBridge.exposeInMainWorld('desk',{
  call:(action,payload)=>ipcRenderer.invoke('desk',action,payload),
  onProgress:callback=>{const handler=(_event,data)=>callback(data);ipcRenderer.on('scan-progress',handler);return()=>ipcRenderer.removeListener('scan-progress',handler);},
  onState:callback=>{const handler=(_event,data)=>callback(data);ipcRenderer.on('state-changed',handler);return()=>ipcRenderer.removeListener('state-changed',handler);}
});
