/* Stage 7B local guide. Deterministic selection of saved content, not AI or diagnosis.
 * Input stays in memory. Never consult accounts, form fields, networks or storage. */
import {validHelp, validServices, findHelp} from './support-model.js?v=20261018-stage7b-r1';
export const LIMIT = 300;
export const ACTIONS = Object.freeze({
 home: 'index.php', lending: 'index.php?page=lending', steps: 'index.php?page=how-it-works',
 calculator: 'index.php?page=calculator', application: 'apply.php',
 it: 'index.php?page=it#help', enquiry: 'index.php?page=it#enquiry',
 software: 'index.php?page=software', portal: 'portal.php', recovery: 'recovery.php', review: 'review.php'
});
export const QUICK = Object.freeze([
 {id:'steps',label:'Loan steps',query:'How it works'},
 {id:'printer',label:'Printer offline',query:'Printer offline'},
 {id:'software',label:'Software tools',query:'Software tools'},
 {id:'portal',label:'Account portal',query:'Account portal'}
]);
const actions = (...ids) => ids.map(id=>({id,label:({home:'Home',lending:'Lending overview',steps:'How it works',calculator:'Open calculator',application:'Application demo',it:'Written IT help',enquiry:'Sample IT request',software:'Software Centre',portal:'Account portalnstration',recovery:'Account-help notice',review:'Review checklist'})[id]}));
export function guideReply(value,data={}){
 const query=typeof value==='string'?value.trim():'';
 if(!query)return {kind:'empty',title:'Choose a starting point',text:'Select a topic or enter a fictional example. No live AI service is connected.',actions:[]};
 if(query.length>LIMIT)return {kind:'invalid',title:'Keep the example short',text:'Use no more than 300 characters and do not enter real personal information.',actions:[]};
 data=data&&typeof data==='object'?data:{};
 const q=query.toLowerCase();
 // Specific account and money requests get a boundary notice, not an invented answer.
 if(/\b(approve|approval|qualify|eligible|pay|payment|balance|owe)\b/.test(q))return {kind:'boundary',title:'No account or payment access',text:'This guide cannot approve a loan, see balances, accept payments or verify eligibility. The calculator illustrates the existing model; the portal contains fictional records only.',actions:actions('calculator','portal')};
 if(/\b(password|reset|login|log in|sign in|otp)\b/.test(q))return {kind:'boundary',title:'Account recovery is not connected',text:'No password, recovery code or account is checked here. Use the account-help notice for the demonstration boundary; do not enter credentials in this guide.',actions:actions('recovery','portal')};
 if(/\b(how it works|loan steps|document|documents|application|apply)\b/.test(q))return {kind:'navigation',title:'Explore the application journey',text:'Start with How it works, then try the five-step fictional application: Borrower, Loan, Repayment, Documents, Review & Sign. Completion does not submit an application.',actions:actions('steps','application')};
 if(/\b(loan|loans|lend|lending|rate|rates|interest|repay|calculator|estimate)\b/.test(q))return {kind:'navigation',title:'Plan with the existing calculator',text:'Choose an amount and one of the existing one-to-four-week terms. The calculator shows principal, interest and an illustrative schedule. This guide does not issue offers or change the lending rules.',actions:actions('lending','calculator','steps')};
 if(/\b(software|asset tracker|sentinel|moneytrack|money tracker|device health|download|installer)\b/.test(q))return {kind:'navigation',title:'Explore the Software Centre',text:'The four-product catalogue shows saved product information. Preview actions explain their destinations; they do not fetch installers, open Sentinel or send messages.',actions:actions('software','enquiry')};
 if(/\b(portal|staff|customer|administrator|admin|task|assigned)\b/.test(q))return {kind:'navigation',title:'Server-authenticated sample workspaces',text:'Sign in with the separate Stage 7B disposable credentials. The server selects your workspace and permitted fictional records. The guide cannot sign you in, read account records or change access.',actions:actions('portal')};
 if(/\b(theme|dark|light|motion|appearance)\b/.test(q))return {kind:'navigation',title:'Make Avesta yours',text:'Use Theme in the page header to choose Light, Dark or Use device setting. Reduce visual motion is available there too. Changing appearance does not reset the current sample form.',actions:[]};
 if(/\b(review checklist|checklist|staging review)\b/.test(q))return {kind:'navigation',title:'Review the staging journeys',text:'The review centre gives you one place to open the main journeys and record this tab’s manual checks. Marking a check does not run a test or certify production readiness.',actions:actions('review')};
 const article=validHelp(data.help)?findHelp(data.help,query):null;
 if(article)return {kind:'saved-help',title:article.title,text:article.answer,steps:[...article.steps],topicId:article.id,
  note:'Saved Avesta guidance, not newly researched, a diagnosis or a live AI response.',
  references:Array.isArray(article.sources)?article.sources.map(s=>s&&typeof s.title==='string'?s.title:'Catalogue reference'):[],
  actions:actions('it','enquiry')};
 if(!validHelp(data.help))return {kind:'unavailable',title:'Written guidance is unavailable',text:'The saved help catalogue is missing or invalid. Navigation still works, but no substitute advice is invented.',actions:actions('it','enquiry')};
 const matches=validServices(data.services)?data.services.filter(s=>(s.name+' '+s.description).toLowerCase().includes(q)).slice(0,3):[];
 if(matches.length)return {kind:'services',title:'From the service catalogue',text:'These saved entries match the phrase. This is a local text match, not a recommendation based on a device inspection.',steps:matches.map(s=>s.name+' — '+s.description),actions:actions('enquiry','it')};
 return {kind:'unmatched',title:'No matching saved guidance',text:'I do not have a matching written example. Try “printer offline” or “loan steps”, or open a fictional IT request. No diagnosis or message has been generated.',actions:actions('it','enquiry')};
}
