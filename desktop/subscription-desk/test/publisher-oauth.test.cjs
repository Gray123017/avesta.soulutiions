'use strict';
const assert=require('node:assert/strict');
const test=require('node:test');
const fs=require('node:fs');const os=require('node:os');const path=require('node:path');
const {readPublisherOAuth}=require('../src/core/publisher-oauth.cjs');
function withConfig(data,fn) {
  const dir=fs.mkdtempSync(path.join(os.tmpdir(),'subtracker-oauth-'));
  try {const file=path.join(dir,'config.json');fs.writeFileSync(file,JSON.stringify(data));return fn(file);} finally{fs.rmSync(dir,{recursive:true,force:true});}
}
test('publisher client IDs are read without secrets',()=>{
  withConfig({googleClientId:'123456.apps.googleusercontent.com',microsoftClientId:'74c0301c-83f9-401b-9af9-21937e4f4992',clientSecret:'DO-NOT-DISTRIBUTE'},file=>{
    const cfg=readPublisherOAuth(file);
    assert.equal(cfg.googleClientId,'123456.apps.googleusercontent.com');
    assert.equal(Object.hasOwn(cfg,'clientSecret'),false);
    assert.ok(Object.isFrozen(cfg));
  });
});
test('blank publisher configuration safely leaves sign-in unavailable',()=>{
  withConfig({googleClientId:'',microsoftClientId:''},file=>{
    assert.equal(readPublisherOAuth(file).googleClientId,'');
    assert.equal(readPublisherOAuth(file).microsoftClientId,'');
  });
});
test('invalid provider IDs fail validation',()=>{
  withConfig({googleClientId:'not-a-client-id'},file=>assert.throws(()=>readPublisherOAuth(file),/malformed/));
  withConfig({microsoftClientId:'xxx'},file=>assert.throws(()=>readPublisherOAuth(file),/malformed/));
});
