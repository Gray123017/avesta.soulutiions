/* Pure presentation/validation model. No network, storage, tickets or account operations. */
export const CATEGORY_NAMES = Object.freeze(['All services','Devices & software','Networks & protection','Digital & data','Advice & training']);
const BY_NAME = Object.freeze({
 'Computer Repair & Maintenance':'Devices & software',
 'Windows & Software Installation':'Devices & software',
 'Networking & Wi-Fi Setup':'Networks & protection',
 'Printer & Scanner Setup':'Devices & software',
 'Website Design & Development':'Digital & data',
 'Database Management':'Digital & data',
 'Data Entry & Data Analysis':'Digital & data',
 'Data Protection, Backup & Recovery':'Networks & protection',
 'CCTV Installation & Maintenance':'Networks & protection',
 'Cybersecurity':'Networks & protection',
 'IT Training & Consultancy':'Advice & training',
 'G.I.T Device Health Monitoring':'Devices & software'
});
export const ADVISE='Not sure — please advise';
export const OTHER='Something else';
export const LIMITS=Object.freeze({name:80,phone:40,location:100,detail:2000,query:300});
export const text = value => typeof value === 'string' ? value.trim() : '';
export function validServices(data){
 return Array.isArray(data) && data.length>0 && data.length<=100 && data.every(s=>
  s && typeof s==='object' && typeof s.name==='string' && text(s.name).length>0 && typeof s.description==='string');
}
export function validHelp(data){
 return Array.isArray(data) && data.length>0 && data.length<=100 && data.every(h=>h &&
  typeof h.id==='string' && typeof h.title==='string' && typeof h.answer==='string' &&
  Array.isArray(h.steps) && h.steps.every(v=>typeof v==='string') &&
  Array.isArray(h.weight) && h.weight.every(v=>typeof v==='string'));
}
export function catalogue(data){
 if(!validServices(data))return [];
 return data.map((s,i)=>({name:s.name,description:s.description,download:s.download===true,index:i,
  category:BY_NAME[s.name] || 'Other services'}));
}
export function filterServices(data,category='All services',query=''){
 const q=text(query).slice(0,160).toLocaleLowerCase();
 return catalogue(data).filter(s=>(category==='All services'||s.category===category) &&
  (!q||(s.name+' '+s.description).toLocaleLowerCase().includes(q)));
}
export function findHelp(data,query){
 const q=text(query).slice(0,LIMITS.query).toLocaleLowerCase();
 if(!q||!validHelp(data))return null;
 let best=null,score=0;
 for(const article of data){
  const n=article.weight.reduce((sum,word)=>sum+(word && q.includes(word.toLocaleLowerCase())?word.length:0),0);
  if(n>score){best=article;score=n;}
 }
 return best;
}
export function serviceForHelp(id,services){
 const map={printer:'Printer & Scanner Setup',wifi:'Networking & Wi-Fi Setup',nointernet:'Networking & Wi-Fi Setup',
  slow:'Computer Repair & Maintenance',wontstart:'Computer Repair & Maintenance',overheat:'Computer Repair & Maintenance',
  virus:'Cybersecurity',files:'Data Protection, Backup & Recovery',backup:'Data Protection, Backup & Recovery',
  cctv:'CCTV Installation & Maintenance'};
 const name=map[id]; return services.some(s=>s.name===name) ? name : ADVISE;
}
export function normalizeEnquiry(raw={}){
 return {name:text(raw.name),phone:text(raw.phone),service:text(raw.service),location:text(raw.location),detail:text(raw.detail),
  acknowledged:raw.acknowledged===true};
}
export function validateEnquiry(raw,services){
 const d=normalizeEnquiry(raw),errors={};
 if(d.name.length<2 || d.name.length>LIMITS.name || !/\b(demo|test)\b/i.test(d.name))
  errors.name='Use a fictional name containing Demo or Test, up to 80 characters.';
 if(!/^(DEMO|TEST)-[A-Z0-9 -]{3,32}$/i.test(d.phone))
  errors.phone='Use a demo contact label, for example DEMO-0000. Do not enter a real number.';
 const names=services.map(s=>s.name);
 if(![ADVISE,OTHER,...names].includes(d.service))errors.service='Choose a listed service or ask for advice.';
 if(d.location.length>LIMITS.location)errors.location='Keep the fictional location within 100 characters.';
 if(d.detail.length<15||d.detail.length>LIMITS.detail)errors.detail='Describe a fictional issue in 15–2,000 characters. Do not include passwords.';
 if(!d.acknowledged)errors.acknowledged='Confirm that this is a fictional, non-submitting demonstration.';
 return errors;
}
export function exampleEnquiry(services){
 return {name:'Demo Client',phone:'DEMO-0000',service:services.some(s=>s.name==='Networking & Wi-Fi Setup')?'Networking & Wi-Fi Setup':ADVISE,
  location:'Demo office, Ndola',detail:'Fictional example: Wi-Fi keeps dropping in the back room of a demo office with eight computers.',acknowledged:false};
}
