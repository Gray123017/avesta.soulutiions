'use strict';
// Run under an X display on Linux: xvfb-run -a node scripts/smoke.cjs
// Install Playwright for development testing: npm install --no-save playwright
const { _electron:electron }=require('playwright');
const fs=require('node:fs'),path=require('node:path'),os=require('node:os'),assert=require('node:assert/strict');
(async()=>{
  let xvfb; const display=String(200+Math.floor(Math.random()*2000));
  if(process.env.SD_XVFB){
    xvfb=require('node:child_process').spawn(process.env.SD_XVFB,[':'+display,'-screen','0','1600x1100x24','-ac','-nolisten','local','-nolisten','unix','-listen','tcp'],{stdio:['ignore','ignore','inherit']});
    process.on('exit',()=>xvfb.kill());
    await new Promise(r=>setTimeout(r,1000));
    process.env.DISPLAY='127.0.0.1:'+display;
  }
  const data=fs.mkdtempSync(path.join(os.tmpdir(),'subscription-desk-ui-'));
  const output=path.resolve('test-output');fs.mkdirSync(output,{recursive:true});
  const executablePath=process.env.SD_PACKAGED_EXECUTABLE||require('electron');
  const args=process.env.SD_PACKAGED_EXECUTABLE?[]:[path.resolve('.')];
  if(process.env.SD_HEADLESS==='1')args.unshift('--ozone-platform=headless','--disable-gpu');
  if(process.env.SD_TEST_NO_SANDBOX==='1')args.unshift('--no-sandbox');
  const application=await electron.launch({executablePath,args,env:{...process.env,SUBSCRIPTION_DESK_TEST_DATA_DIR:data},timeout:45000});
  const page=await application.firstWindow({timeout:20000});page.setDefaultTimeout(15000);const errors=[];page.on('pageerror',e=>{errors.push(e.message);console.error('PAGE ERROR:',e.message);});page.on('console',m=>{if(m.type()==='error')console.error('CONSOLE:',m.text());});
  try{
    await page.getByRole('heading',{name:'Your subscription overview'}).waitFor();
    assert.equal(await page.evaluate(()=>typeof require),'undefined');
    await page.setViewportSize({width:1440,height:1000});
    const noOverflow=async()=>{const sizes=await page.evaluate(()=>({body:[document.body.scrollWidth,document.body.clientWidth],main:[document.querySelector('main').scrollWidth,document.querySelector('main').clientWidth]}));for(const [name,[scroll,client]]of Object.entries(sizes))assert.ok(scroll<=client+1,`${name} overflows: ${scroll} > ${client}`);};
    const clearToast=async()=>page.evaluate(()=>{const el=document.querySelector('#toast');if(el.matches(':popover-open'))el.hidePopover();el.classList.add('hidden');});
    await page.getByRole('button',{name:'Explore sample data'}).click();
    await page.locator('tr[data-row]').first().waitFor();
    assert.equal(await page.locator('tr[data-row]').count(),5);
    await clearToast();await page.evaluate(()=>document.querySelector('main').scrollTop=0);
    await noOverflow();await page.screenshot({path:path.join(output,'overview.png')});
    for(const width of [1050,820]){
      await page.setViewportSize({width,height:width===820?640:800});await noOverflow();
      await page.screenshot({path:path.join(output,`overview-${width}.png`)});
    }
    // Compact navigation remains operable; the manual choice survives a renderer reload.
    await page.getByRole('button',{name:'Expand navigation',exact:true}).click();await noOverflow();
    await page.reload();await page.getByRole('heading',{name:'Your subscription overview'}).waitFor();
    await page.getByRole('button',{name:'Collapse navigation',exact:true}).click();
    await page.setViewportSize({width:1440,height:1000});
    await page.getByRole('button',{name:'Expand navigation',exact:true}).click();
    // Space opens a record, Tab remains in its modal panel, Escape returns to that record.
    await page.locator('tr[data-row]').first().focus();await page.keyboard.press('Space');
    assert.equal(await page.locator('#detail-panel').evaluate(el=>el.open),true);
    await page.keyboard.press('Shift+Tab');
    assert.ok(await page.evaluate(()=>document.querySelector('#detail-panel').contains(document.activeElement)));
    await page.keyboard.press('Tab');await page.keyboard.press('Escape');
    await page.waitForFunction(()=>!document.querySelector('#detail-panel').open);
    assert.equal(await page.evaluate(()=>document.activeElement.dataset.row),'demo-0');
    await page.locator('tr[data-row]').first().click();
    await page.locator('#record-form [name=usage]').selectOption('Unused');
    await page.locator('#record-form [name=decision]').selectOption('Cancel');
    await page.locator('#record-form [name=notes]').fill('Test review <script>alert(1)</script>');
    await page.getByRole('button',{name:'Save review',exact:true}).click();
    await page.waitForFunction(()=>document.querySelector('#toast').textContent==='Review saved.');
    const saved=await page.evaluate(()=>window.desk.call('state'));
    assert.equal(saved.data.items[0].decision,'Cancel');assert.equal(saved.data.items[0].usage,'Unused');
    assert.equal(await page.locator('#toast').evaluate(el=>el.matches(':popover-open')),true);
    await clearToast();await page.locator('#detail-panel').evaluate(el=>el.scrollTop=0);
    await page.screenshot({path:path.join(output,'record-review.png')});
    await page.getByRole('button',{name:'Close subscription details'}).click();
    await page.locator('.nav[data-view="subscriptions"]').click();
    await page.locator('#search').fill('Music');assert.equal(await page.locator('tr[data-row]').count(),1);
    assert.equal(await page.locator('#search').evaluate(el=>el===document.activeElement),true);
    await page.locator('#search').fill('');await page.locator('#filter').selectOption('attention');assert.equal(await page.locator('tr[data-row]').count(),1);
    await page.locator('#filter').selectOption('all');
    await page.setViewportSize({width:820,height:640});
    await page.getByRole('button',{name:'Collapse navigation',exact:true}).click();await noOverflow();
    await page.screenshot({path:path.join(output,'subscriptions-compact.png')});
    await page.locator('.nav[data-view="settings"]').click();
    await page.locator('input[name=zone]').fill('Africa/Lusaka');
    await page.getByRole('button',{name:'Save preferences',exact:true}).click();
    await clearToast();await page.evaluate(()=>document.querySelector('main').scrollTop=0);await noOverflow();
    await page.screenshot({path:path.join(output,'settings-compact.png')});
    await page.setViewportSize({width:1440,height:1000});await page.getByRole('button',{name:'Expand navigation',exact:true}).click();
    await noOverflow();await page.screenshot({path:path.join(output,'settings.png')});
    await page.emulateMedia({reducedMotion:'reduce'});
    await page.locator('.nav[data-view="subscriptions"]').click();await page.locator('tr[data-row]').first().click();
    assert.equal(await page.locator('#detail-panel').evaluate(el=>getComputedStyle(el).animationDuration),'1e-05s');
    await page.keyboard.press('Escape');await page.waitForFunction(()=>!document.querySelector('#detail-panel').open);
    await page.getByRole('button',{name:'Export records',exact:true}).click();
    await page.screenshot({path:path.join(output,'export-dialog.png')});
    await page.keyboard.press('Escape');assert.equal(await page.locator('#export-dialog').evaluate(el=>el.open),false);
    // Exercise desktop IPC export, PDF rendering, and JSON import through mocked native file dialogs.
    const state=await page.evaluate(()=>window.desk.call('state'));assert.equal(state.data.settings.zone,'Africa/Lusaka');
    for(const format of ['json','xlsx','pdf']){
      const file=path.join(output,'ui-export.'+format);
      await application.evaluate(({dialog},file)=>{dialog.showSaveDialog=async()=>({canceled:false,filePath:file});},file);
      const r=await page.evaluate(format=>window.desk.call('export',{format}),format);assert.ok(r.ok,r.error);assert.ok(fs.statSync(file).size>100);
    }
    const invalid=await page.evaluate(()=>window.desk.call('edit',{id:'demo-0',name:'Must not persist',dueDate:'2026-02-30'}));assert.equal(invalid.ok,false);
    const unchanged=await page.evaluate(()=>window.desk.call('state'));assert.equal(unchanged.data.items[0].name,'Cloud hosting');
    // Provider actions must be confirmed and cannot mark a payment/cancellation completed.
    await application.evaluate(({dialog,shell})=>{dialog.showMessageBox=async()=>({response:0});shell.openExternal=async()=>{throw new Error('Should not open after cancelled confirmation');};});
    const cancelled=await page.evaluate(()=>window.desk.call('provider',{id:'demo-0',action:'cancel'}));assert.equal(cancelled.data.cancelled,true);
    assert.equal((await page.evaluate(()=>window.desk.call('state'))).data.items[0].statusKind,'payment_failed');
    assert.deepEqual(errors,[]);
    console.log(JSON.stringify({result:'passed',checks:['app launch','renderer isolation','820/1050/1440px layouts','sidebar persistence','keyboard modal focus','reduced motion','export dialog','demo load','review persistence','search','settings','Excel export','PDF export','backup export','atomic validation','provider confirmation'],output}));
  }catch(error){console.error('BODY:',(await page.locator('body').innerText()).slice(-4000));await page.screenshot({path:path.join(output,'failure.png'),fullPage:true});throw error;}finally{await application.close();xvfb?.kill();fs.rmSync(data,{recursive:true,force:true});}
})().catch(e=>{console.error(e);process.exitCode=1;});
