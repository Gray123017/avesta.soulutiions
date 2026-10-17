'use strict';
const {hash,validDate}=require('./model.cjs');
const addDays=(date,days)=>{const d=new Date(date+'T12:00:00Z');d.setUTCDate(d.getUTCDate()+days);return d.toISOString().slice(0,10);};
function todayInZone(zone,now=new Date()){return new Intl.DateTimeFormat('en-CA',{timeZone:zone,year:'numeric',month:'2-digit',day:'2-digit'}).format(now);}
function reminderPlan(row,zone,now=new Date()){
  if(!validDate(row.dueDate)||!['explicit','user-confirmed'].includes(row.dateConfidence))throw new Error('Confirm the exact date before creating a reminder. Estimated dates are not scheduled.');
  if(!row.reviewed)throw new Error('Review the account and date evidence, then mark this record reviewed.');
  new Intl.DateTimeFormat('en',{timeZone:zone}).format(now);
  if(row.dueDate<todayInZone(zone,now))throw new Error('This date has passed. Confirm the next deadline first.');
  const alertDate=addDays(row.dueDate,-5);
  if(alertDate<todayInZone(zone,now))throw new Error('The five-day reminder date has passed. Review this subscription now or choose a later confirmed deadline.');
  return{alertDate,dueDate:row.dueDate,zone,key:hash(row.id+'|'+row.dueDate+'|'+row.eventKind+'|'+zone),summary:'Review '+row.name+' — '+row.eventKind.replaceAll('_',' ')+' in 5 days',description:'Subscription Desk reminder\nDeadline: '+row.dueDate+'\nType: '+row.eventKind+'\nAccount: '+row.account+'\nEvidence: '+(row.dateEvidence||'User-confirmed date')+'\nReview the provider account before paying, renewing or cancelling.\nNo automatic financial action will occur.'};
}
function calendarEvent(row,zone,now){const p=reminderPlan(row,zone,now);return{id:'sd'+p.key,summary:p.summary,description:p.description,start:{dateTime:p.alertDate+'T09:00:00',timeZone:zone},end:{dateTime:p.alertDate+'T09:10:00',timeZone:zone},visibility:'private',transparency:'transparent',reminders:{useDefault:false,overrides:[{method:'popup',minutes:0},{method:'email',minutes:0}]},extendedProperties:{private:{subscriptionDesk:'1',subscriptionId:row.id,deadline:row.dueDate}},guestsCanInviteOthers:false,guestsCanModify:false};}
function icsEscape(s){return String(s).replace(/\\/g,'\\\\').replace(/\n/g,'\\n').replace(/[,;]/g,'\\$&').replace(/\r/g,'');}
function ics(rows,zone,now=new Date()){
  const lines=['BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//Subscription Desk//EN','CALSCALE:GREGORIAN','METHOD:PUBLISH'];
  for(const row of rows){const p=reminderPlan(row,zone,now);lines.push('BEGIN:VEVENT','UID:'+p.key+'@subscriptiondesk.local','DTSTAMP:'+now.toISOString().replace(/[-:]|\.\d{3}/g,''),'DTSTART;VALUE=DATE:'+p.alertDate.replaceAll('-',''),'DTEND;VALUE=DATE:'+addDays(p.alertDate,1).replaceAll('-',''),'SUMMARY:'+icsEscape(p.summary),'DESCRIPTION:'+icsEscape(p.description),'TRANSP:TRANSPARENT','CLASS:PRIVATE','BEGIN:VALARM','ACTION:DISPLAY','DESCRIPTION:'+icsEscape(p.summary),'TRIGGER:PT9H','END:VALARM','END:VEVENT');}
  lines.push('END:VCALENDAR');
  // RFC 5545 folding uses octets, so Unicode titles remain valid.
  return lines.flatMap(line=>{const out=[];let part='';let n=0;for(const ch of line){const bytes=Buffer.byteLength(ch);if(n+bytes>73){out.push(part);part=' ';n=1;}part+=ch;n+=bytes;}out.push(part);return out;}).join('\r\n')+'\r\n';
}
module.exports={addDays,todayInZone,reminderPlan,calendarEvent,ics};
