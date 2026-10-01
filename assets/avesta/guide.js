export function guideAnswer(message,guides){
 const q=message.toLowerCase();
 const source=(title,url)=>({title,url});
 if(/\b(contact|email|phone|hours|address|location)\b/.test(q))return {answer:'Contact Avesta at info@avesta.solutions or 0971 013 108 / 0769 974 200. Office: House No. 3, Thom Avenue, Kansenshi, Ndola. Published hours: Monday–Saturday, 08:00–17:00.',sources:[source('Avesta contact details','/index.php?page=contact')]};
 if(/\b(loan|borrow|interest|repay|apply|lending)\b/.test(q))return {answer:'Avesta’s published loan terms are 1–4 weeks, with flat interest of 15%, 20%, 25% and 30% respectively. Use the calculator for an estimate. Your written agreement confirms the terms; an application is not approval. The assistant cannot access your application or approve a loan.',sources:[source('Loan terms','/index.php?page=lending'),source('Loan calculator','/index.php?page=calculator'),source('Apply','/apply.php')]};
 let best=null,score=0;
 for(const g of guides){const n=[...(g.weight||[]),...(g.hint||[])].reduce((s,w)=>s+(q.includes(w.toLowerCase())?w.length:0),0);if(n>score){score=n;best=g;}}
 if(best)return {answer:best.title+'\n\n'+best.answer+'\n\n'+best.steps.map((s,i)=>(i+1)+'. '+s).join('\n')+(best.warn?'\n\n'+best.warn:'')+'\n\n'+best.call,sources:best.sources||[]};
 return {answer:'I can help with Avesta services, lending information and common IT problems. Tell me the device, model and exact error, without passwords or personal documents. For help from the team, contact info@avesta.solutions or 0769 974 200.',sources:[source('Avesta IT services','/index.php?page=it')]};
}
