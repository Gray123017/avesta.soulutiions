/* Public catalogue validation only. No installer/network/storage operations. */
const text = (s,max=600) => typeof s === 'string' && s.trim().length > 0 && s.length <= max;
const kinds = new Set(['download','product','notes','support']);
export function validDestination(value,kind) {
 if (!text(value,500) || /[\x00-\x20\x7f\\<>]/.test(value)) return false;
 if(kind==='support')return /^mailto:info@avesta\.solutions\?subject=[A-Za-z0-9%._~-]+$/.test(value) && !/%0[ad]/i.test(value);
 if (!/^\/(downloads|sentinel)\//.test(value) || /[?#%]/.test(value)) return false;
 return value.split('/').every(part=>part!=='.'&&part!=='..');
}
export function validSoftware(data) {
 if(!Array.isArray(data)||data.length<1||data.length>24)return false;
 const ids=new Set();
 return data.every(p=>{
  if(!p||typeof p!=='object'||!text(p.id,50)||!/^\w[\w-]*$/.test(p.id)||ids.has(p.id))return false;
  ids.add(p.id);
  return text(p.name,100)&&text(p.short,50)&&text(p.icon,4)&&text(p.badge,80)&&text(p.description,900)
   &&Array.isArray(p.meta)&&p.meta.length>0&&p.meta.length<=8&&p.meta.every(v=>text(v,100))
   &&Array.isArray(p.features)&&p.features.length>0&&p.features.length<=12&&p.features.every(v=>text(v,300))
   &&Array.isArray(p.actions)&&p.actions.length>0&&p.actions.length<=4&&p.actions.every(a=>a&&text(a.label,80)&&kinds.has(a.kind)&&validDestination(a.destination,a.kind));
 });
}
export function selectedProduct(products,hash='') {
 if(!validSoftware(products))return null;
 const requested=/^#product-([A-Za-z0-9_-]+)$/.exec(String(hash));
 return products.find(p=>p.id===requested?.[1])||products[0];
}
export function actionExplanation(kind) {
 return ({download:'No installer has been requested or downloaded.',product:'The live product has not been opened.',notes:'The setup-notes file has not been fetched.',support:'No email application has opened and no support message has been sent.'})[kind]||'No action has been sent.';
}
