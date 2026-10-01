export const rates={1:15,2:20,3:25,4:30};
export const statuses=['Pending','Under Review','Approved','Rejected','Disbursed','Repaid'];
export const frequencies=['Weekly','Bi-weekly','Monthly','Lump sum'];
export function money(value){return new Intl.NumberFormat('en-ZM',{style:'currency',currency:'ZMW',minimumFractionDigits:2}).format(Number(value)||0);}
export function quote(amount,weeks,frequency='Lump sum'){
 const cents=Math.round(Number(amount)*100); weeks=Number(weeks);
 if(!Number.isFinite(cents)||cents<=0||cents>100000000||!rates[weeks])throw new Error('Enter a valid amount and choose a term from 1 to 4 weeks.');
 const interest=Math.round(cents*rates[weeks]/100),total=cents+interest;
 const count=frequency==='Weekly'?weeks:frequency==='Bi-weekly'?Math.ceil(weeks/2):1;
 const base=Math.floor(total/count),remainder=total-base*count;
 return {principal:cents,interest,total,rate:rates[weeks],count,instalments:Array.from({length:count},(_,i)=>base+(i<remainder?1:0))};
}
export function today(){return new Intl.DateTimeFormat('en-CA',{timeZone:'Africa/Lusaka',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());}
export function addDays(date,n){return new Date(Date.parse(date+'T12:00:00Z')+n*86400000).toISOString().slice(0,10);}
export function schedule(amount,weeks,frequency,start){
 const q=quote(amount,weeks,frequency);
 return q.instalments.map((value,i)=>({amount:value,date:addDays(start,Math.min(Number(weeks)*7,frequency==='Weekly'?(i+1)*7:frequency==='Bi-weekly'?(i+1)*14:Number(weeks)*7))}));
}
export function validateApplication(d){
 const required=['name','nrc','dob','phone','address','occupation','income','security','purpose','method','frequency','repaymentMethod','signature'];
 for(const k of required)if(!String(d[k]??'').trim())throw new Error('Please complete '+k+'.');
 if(String(d.name).length>100||String(d.address).length>1000||String(d.purpose).length>2000)throw new Error('Please shorten the application text.');
 if(!/^\d{6}\/\d{2}\/\d$/.test(d.nrc))throw new Error('Enter the NRC in 123456/78/9 format.');
 if(!/^(?:\+?260|0)[0-9]{9}$/.test(d.phone.replace(/[\s-]/g,'')))throw new Error('Enter a valid Zambian phone number.');
 if(d.email&&!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(d.email))throw new Error('Enter a valid email address.');
 const cutoff=new Date(today()+'T12:00:00Z');cutoff.setUTCFullYear(cutoff.getUTCFullYear()-18);
 if(!/^\d{4}-\d{2}-\d{2}$/.test(d.dob)||!Number.isFinite(Date.parse(d.dob))||d.dob>cutoff.toISOString().slice(0,10))throw new Error('Applicant must be at least 18 years old.');
 if(!Number.isFinite(Number(d.income))||Number(d.income)<0)throw new Error('Enter a valid monthly income.');
 if(!frequencies.includes(d.frequency))throw new Error('Choose a repayment schedule.');
 if(!['Collateral','Salary check-off','Employer letter','Guarantor','None'].includes(d.security))throw new Error('Choose a security type.');
 if(d.security!=='None'&&!String(d.securityDetails||'').trim())throw new Error('Provide the details of your security arrangement.');
 if(d.method!=='Cash'&&!String(d.account||'').trim())throw new Error('Provide your disbursement account details.');
 if(!d.consent||!d.declaration)throw new Error('Please confirm the declaration and privacy consent.');
 if(d.signature.trim().toLowerCase()!==d.name.trim().toLowerCase())throw new Error('Type your full name to acknowledge the application.');
 return quote(d.amount,d.weeks,d.frequency);
}
export function escapeHTML(value){return String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
export function loanBook(records,now=today()){
 return records.filter(r=>['Disbursed','Repaid'].includes(r.status)).reduce((b,r)=>{
  const paid=Math.min(r.paid||0,r.total),balance=Math.max(0,r.total-(r.paid||0));
  b.balance+=balance;b.collected+=paid;b.total+=r.total;
  if(balance&&r.disbursed){const due=addDays(r.disbursed,r.weeks*7);if(due<now){b.overdue+=balance;b.overdueCount++;}else if(due<=addDays(now,7))b.dueSoon+=balance;}
  return b;
 },{balance:0,collected:0,total:0,overdue:0,overdueCount:0,dueSoon:0});
}
