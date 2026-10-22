/* Stage 2B model: fictional application state only. No storage, API or authorization logic. */
import {rates, frequencies, quote, schedule, today} from './domain.js?v=20261003-steady-navigation';
import {calculatorOptions} from './routing.js?v=20261018-stage7b-r1';
export {rates, frequencies};
export const steps=['Borrower','Loan','Repayment','Documents','Review & Sign'];
export const securityOptions={collateral:'Collateral',checkoff:'Salary check-off',employer_letter:'Employer letter',guarantor:'Guarantor',none:'None / discuss with Avesta'};
export const methods={cash:'Cash',mobile_airtel:'Airtel Money',mobile_mtn:'MTN MoMo',zamtel:'Zamtel',bank_zanaco:'ZANACO',bank_fnb:'FNB Zambia',bank_stanbic:'Stanbic',bank_other:'Other bank'};
export const MAX_FILE_BYTES=5*1024*1024;
export const allDocuments={front:'NRC — front side',back:'NRC — back side',collateral:'Collateral photo',letter:'Employer confirmation letter',guarantor:'Guarantor NRC (both sides in one dummy file)',payslip:'Payslip / proof of income',bank:'Bank statement',utility:'Utility bill',photo:'Passport-size photo'};
export function initialState(search='') {
 const options=calculatorOptions(new URLSearchParams(search));
 return {name:'',identity:'',dob:'',phone:'',address:'',email:'',occupation:'',income:'',security:'',collateral:[{description:'',value:''}],employer:'',payrollContact:'',payrollPhone:'',payrollNumber:'',guarantorName:'',guarantorIdentity:'',guarantorPhone:'',guarantorRelationship:'',guarantorOccupation:'',guarantorIncome:'',referenceName:'',referencePhone:'',referenceRelationship:'',amount:options.get('amount')||'1000.00',purpose:'',receive:'',start:options.get('start')||today(),weeks:options.get('weeks')||'2',frequency:options.get('frequency')||'Lump sum',repay:'',documents:{},demoOnly:false,reviewed:false,signatureName:''};
}
export function fictionalExample(search='') {
 return {...initialState(search),name:'Demo Borrower',identity:'DEMO-ID-001',dob:'1995-01-15',phone:'0000000000',address:'Demo Address, Example Town',email:'borrower@example.test',occupation:'Demo role at Example Company',income:'3500.00',security:'collateral',collateral:[{description:'Demo laptop — not offered as real security',value:'2500.00'}],employer:'Example Company',payrollContact:'Demo Payroll Officer',payrollPhone:'0000000000',payrollNumber:'DEMO-PAY-001',guarantorName:'Demo Guarantor',guarantorIdentity:'DEMO-ID-002',guarantorPhone:'0000000000',guarantorRelationship:'Demo colleague',guarantorOccupation:'Demo role at Example Company',guarantorIncome:'4000.00',purpose:'Sample stock purchase for interface testing',receive:'mobile_mtn',repay:'mobile_mtn',demoOnly:true};
}
export function validDate(value) {return Boolean(calculatorOptions(new URLSearchParams({start:value})).get('start'));}
export function validMoney(value,allowZero=false) {return typeof value==='string' && /^\d+(?:\.\d{1,2})?$/.test(value) && Number(value)>=(allowZero?0:.01) && Number(value)<=1000000;}
export function requiredDocs(security) {return ['front','back',...({collateral:['collateral'],employer_letter:['letter'],guarantor:['guarantor']}[security]||[])];}
export function visibleDocs(security) {return [...requiredDocs(security),'payslip','bank','utility','photo'];}
export function estimate(state) {
 if (!validMoney(state.amount)) throw new Error('Enter ZMW 0.01–1,000,000 with no more than two decimal places.');
 if (!Object.hasOwn(rates,state.weeks)) throw new Error('Choose a term from 1 to 4 weeks.');
 if (!frequencies.includes(state.frequency)) throw new Error('Choose a listed repayment frequency.');
 if (!validDate(state.start)) throw new Error('Choose a valid illustrative disbursement date.');
 return {quote:quote(state.amount,state.weeks,state.frequency),schedule:schedule(state.amount,state.weeks,state.frequency,state.start)};
}
export function validateStep(state,step) {
 const errors=[]; const err=(field,message)=>errors.push({field,message});
 const text=(field,label,max=120)=>{if(!String(state[field]||'').trim())err(field,`${label} is needed for the demonstration.`);else if(String(state[field]).length>max)err(field,`${label} must be ${max} characters or fewer.`);};
 const money=(field,label,zero=false)=>{if(!validMoney(state[field],zero))err(field,`${label}: use ${zero?'0':'0.01'}–1,000,000 with up to two decimals (demo limit).`);};
 if(step===0){
  if(!state.demoOnly)err('demoOnly','Confirm that you will use fictional information and dummy files only.');
  for(const [f,l,max] of [['name','Fictional borrower name',100],['identity','Demo ID',60],['phone','Dummy phone',30],['address','Fictional address',200],['occupation','Fictional occupation / employer',120]])text(f,l,max);
  if(state.identity&&!/^(DEMO|TEST)-[A-Za-z0-9-]+$/.test(state.identity))err('identity','Use a clearly fictional ID beginning DEMO- or TEST-. Do not enter a real NRC.');
  if(state.dob&&!validDate(state.dob))err('dob','Choose a valid fictional date of birth.');
  if(state.email&&(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(state.email)||state.email.length>120))err('email','Enter a valid-format dummy email, such as borrower@example.test.');
  money('income','Fictional monthly income',true);
  if(!Object.hasOwn(securityOptions,state.security))err('security','Choose a security option to test its conditional fields.');
  if(state.security==='collateral'){
   if(!Array.isArray(state.collateral)||!state.collateral.length||state.collateral.length>5)err('collateral','Add one to five fictional collateral items (preview limit).');
   else state.collateral.forEach((x,i)=>{if(!x.description?.trim()||x.description.length>160)err(`collateral-description-${i}`,`Describe fictional collateral item ${i+1} (up to 160 characters).`);if(!validMoney(x.value))err(`collateral-value-${i}`,`Enter a positive illustrative value for item ${i+1}.`);});
  }
  if(['checkoff','employer_letter'].includes(state.security))text('employer','Fictional employer');
  if(state.security==='checkoff'){text('payrollContact','Fictional payroll contact');text('payrollNumber','Dummy payroll number');}
  if(state.security==='guarantor'){
   for(const [f,l] of [['guarantorName','Fictional guarantor name'],['guarantorIdentity','Guarantor demo ID'],['guarantorPhone','Dummy guarantor phone'],['guarantorRelationship','Fictional relationship']])text(f,l);
   if(state.guarantorIdentity&&!/^(DEMO|TEST)-[A-Za-z0-9-]+$/.test(state.guarantorIdentity))err('guarantorIdentity','Use a guarantor ID beginning DEMO- or TEST-.');
   if(state.guarantorIncome)money('guarantorIncome','Fictional guarantor income',true);
  }
 }
 if(step===1){money('amount','Sample loan amount');text('purpose','Fictional loan purpose',500);if(!Object.hasOwn(methods,state.receive))err('receive','Choose a disbursement method to preview.');if(!validDate(state.start))err('start','Choose a valid illustrative disbursement date.');}
 if(step===2){if(!Object.hasOwn(rates,state.weeks))err('weeks','Choose one of the four existing loan terms.');if(!frequencies.includes(state.frequency))err('frequency','Choose a listed frequency.');if(!Object.hasOwn(methods,state.repay))err('repay','Choose a repayment method to preview.');}
 if(step===3){for(const id of requiredDocs(state.security))if(!state.documents[id]?.ready)err(`doc-${id}`,`Add a dummy ${allDocuments[id]} or use the generated sample documents.`);}
 if(step===4){text('signatureName','Fictional review name',100);if(!state.reviewed)err('reviewed','Confirm this is a demonstration, not a signed agreement or application.');}
 return errors;
}
export function firstInvalidStep(state){for(let i=0;i<5;i++){const errors=validateStep(state,i);if(errors.length)return {step:i,errors};}return null;}
export function classifyFile(name,bytes,size,type='') {
 if(!size)throw new Error('This file is empty. Choose a labelled dummy file.');
 if(size>MAX_FILE_BYTES)throw new Error('Each dummy file must be 5 MB or smaller.');
 const ext=String(name).toLowerCase().split('.').pop();
 const png=bytes.length>=8&&[137,80,78,71,13,10,26,10].every((b,i)=>b===bytes[i]);
 const jpg=bytes.length>=3&&bytes[0]===255&&bytes[1]===216&&bytes[2]===255;
 const pdf=bytes.length>=5&&String.fromCharCode(...bytes.slice(0,5))==='%PDF-';
 const detected=png&&ext==='png'?'image/png':jpg&&['jpg','jpeg'].includes(ext)?'image/jpeg':pdf&&ext==='pdf'?'application/pdf':null;
 if(!detected || (type&&type!==detected))throw new Error('Use a genuine PNG, JPG or PDF dummy file with a matching extension.');
 return detected;
}
