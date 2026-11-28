/* The terms a borrower can choose, and what each costs. Mirrors the "terms"
   block of assets/avesta/rates.json, which is the published table the server
   prices from; scripts/check-rates.mjs fails the build if the two disagree.
   Eight weeks was priced there from 27 November 2026 so the overdue ladder
   had an honest figure to pass at day 56, and opened for sale on 28 November. */
export const rates={1:15,2:20,3:25,4:30,8:55};
export const statuses=['Pending','Under Review','Approved','Rejected','Disbursed','Repaid'];
export const frequencies=['Weekly','Bi-weekly','Monthly','Lump sum'];
export function money(value){return new Intl.NumberFormat('en-ZM',{style:'currency',currency:'ZMW',minimumFractionDigits:2}).format(Number(value)||0);}
export function quote(amount,weeks,frequency='Lump sum'){
 const cents=Math.round(Number(amount)*100); weeks=Number(weeks);
 if(!Number.isFinite(cents)||cents<=0||cents>100000000||!rates[weeks])throw new Error('Enter a valid amount and choose one of the published terms: '+Object.keys(rates).join(', ')+' weeks.');
 const interest=Math.round(cents*rates[weeks]/100),total=cents+interest;
 /* Monthly used to mean one payment whatever the length, which was true while
    nothing longer than four weeks could be bought. At eight weeks that is a
    single lump sum with "Monthly" written on it, so it now counts months. */
 const count=frequency==='Weekly'?weeks:frequency==='Bi-weekly'?Math.ceil(weeks/2):frequency==='Monthly'?Math.ceil(weeks/4):1;
 const base=Math.floor(total/count),remainder=total-base*count;
 return {principal:cents,interest,total,rate:rates[weeks],count,instalments:Array.from({length:count},(_,i)=>base+(i<remainder?1:0))};
}
export function today(){return new Intl.DateTimeFormat('en-CA',{timeZone:'Africa/Lusaka',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());}
export function addDays(date,n){return new Date(Date.parse(date+'T12:00:00Z')+n*86400000).toISOString().slice(0,10);}
export function schedule(amount,weeks,frequency,start){
 const q=quote(amount,weeks,frequency);
 return q.instalments.map((value,i)=>({amount:value,date:addDays(start,Math.min(Number(weeks)*7,frequency==='Weekly'?(i+1)*7:frequency==='Bi-weekly'?(i+1)*14:frequency==='Monthly'?(i+1)*28:Number(weeks)*7))}));
}
export function escapeHTML(value){return String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
