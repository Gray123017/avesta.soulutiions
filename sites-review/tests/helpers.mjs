import {DatabaseSync} from 'node:sqlite';
import {readFileSync,readdirSync} from 'node:fs';
export function environment(){
 const sqlite=new DatabaseSync(':memory:');
 sqlite.exec('PRAGMA foreign_keys=ON');
 for(const file of readdirSync('drizzle').filter(f=>f.endsWith('.sql')).sort())sqlite.exec(readFileSync('drizzle/'+file,'utf8'));
 const DB={prepare(sql){let values=[];return{bind(...v){values=v;return this;},async first(){return sqlite.prepare(sql).get(...values)||null;},async all(){return{results:sqlite.prepare(sql).all(...values)};},async run(){const r=sqlite.prepare(sql).run(...values);return{meta:{changes:Number(r.changes)}};}};},async batch(statements){sqlite.exec('BEGIN');try{const r=[];for(const s of statements)r.push(await s.run());sqlite.exec('COMMIT');return r;}catch(err){sqlite.exec('ROLLBACK');throw err;}}};
 const objects=new Map();
 const BUCKET={async head(key){return objects.has(key)?{}:null;},async put(key,value,options){objects.set(key,{value,options});},async get(key){const object=objects.get(key);return object?{body:object.value}:null;},async delete(key){objects.delete(key);}};
 return {DB,BUCKET,sqlite,objects};
}
export const validData={name:'Avesta Test Applicant',nrc:'123456/78/9',dob:'1990-01-01',phone:'0971013108',email:'test@example.invalid',address:'Test address',occupation:'Test role',income:3000,security:'None',purpose:'Test application',amount:1000,weeks:2,frequency:'Weekly',method:'Cash',repaymentMethod:'Cash',signature:'Avesta Test Applicant',consent:true,declaration:true};
export function form(data=validData){
 const f=new FormData();f.set('payload',JSON.stringify(data));f.set('nrcFront',new File(['%PDF-test'], 'front.pdf',{type:'application/pdf'}));f.set('nrcBack',new File(['%PDF-test'],'back.pdf',{type:'application/pdf'}));return f;
}
export function call(worker,env,path,options={},user='test-owner'){
 const headers=new Headers(options.headers);if(user)headers.set('oai-authenticated-user-id',user);headers.set('oai-authenticated-user-email','review@example.invalid');
 return worker.fetch(new Request('https://review.invalid'+path,{...options,headers}),env,{});
}
export const jsonOptions=(method,data)=>({method,headers:{'content-type':'application/json'},body:JSON.stringify(data)});
