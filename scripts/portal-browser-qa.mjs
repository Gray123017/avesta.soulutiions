import {readFileSync, mkdirSync} from 'node:fs';
import {createServer} from 'node:http';
import assert from 'node:assert/strict';
import {chromium} from 'playwright';
const source = readFileSync('app/admin.php', 'utf8');
function extract(name) {
  const start = source.indexOf('function ' + name + '(');
  assert(start >= 0, name + ' exists');
  const end = source.indexOf('\n}\n', start) + 3;
  return source.slice(start, end);
}
let fixture = source.replace(/<\?php[\s\S]*?\?>/g, '').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '').replace(/<link\b[^>]*>/gi, '').replace(/<img\b[^>]*>/gi, '');
const behaviour = ['toggleSidebar', 'updateSidebarToggle', 'showPage', 'toggleWaPanel'].map(extract).join('\n') + `
function loadRecords(){} function applyFilters(){} function renderTable(){}
function renderDashboard(){} function showPage_settings_init(){} function afxInit(){}
function updateWaStatus(){}
document.getElementById('login-screen').style.display='none';
document.getElementById('app').style.display='block';
document.getElementById('topbar-date').textContent='Sun, 4 Oct 2026';
window.addEventListener('resize',updateSidebarToggle);
updateSidebarToggle();
document.querySelectorAll('.nav-item').forEach(el=>el.addEventListener('click',()=>{if(innerWidth<=768)toggleSidebar(true);}));
document.addEventListener('keydown',e=>{if(e.key==='Escape'){toggleWaPanel(true);toggleSidebar(true);}});
`;
fixture = fixture.replace('</body>', '<script>' + behaviour + '</script></body>');
const server = createServer((req,res)=>{res.writeHead(200,{'Content-Type':'text/html; charset=utf-8'}); res.end(fixture);});
await new Promise(resolve=>server.listen(8765,'127.0.0.1',resolve));
const browser = await chromium.launch();
mkdirSync('qa-screenshots',{recursive:true});
try {
 for (const [width,height] of [[320,700],[390,844],[768,850],[1280,900],[390,450]]) {
  const page=await browser.newPage({viewport:{width,height}});
  await page.route('**/*',route=>route.request().url().startsWith('http://127.0.0.1:8765/')?route.continue():route.abort());
  await page.goto('http://127.0.0.1:8765/');
  const state=await page.evaluate(()=>{
    const r=s=>document.querySelector(s).getBoundingClientRect();
    return {headerBottom:r('.topbar').bottom,dashboardTop:r('#page-dashboard').top,cardParent:document.getElementById('borrower-notifications').parentElement.id};
  });
  assert.equal(state.cardParent,'page-dashboard');
  assert(state.dashboardTop>=state.headerBottom, 'dashboard clears sticky header');
  if(width<=768){
   await page.getByRole('button',{name:'Open sidebar menu'}).click();
   await page.waitForFunction(()=>document.querySelector('.sidebar').getBoundingClientRect().left>=-1);
   assert.equal(await page.locator('#sidebar-toggle').getAttribute('aria-expanded'),'true');
   assert(await page.evaluate(()=>document.elementFromPoint(100,25)?.closest('.sidebar')!==null),'sidebar above header');
   await page.screenshot({path:'qa-screenshots/menu-'+width+'x'+height+'.png'});
   await page.getByRole('link',{name:'All Applications'}).click();
   assert.equal(await page.locator('#sidebar-toggle').getAttribute('aria-expanded'),'false');
   assert(await page.locator('#page-applications').isVisible());
   assert(!(await page.locator('#borrower-notifications').isVisible()),'card stays on dashboard');
   await page.getByRole('button',{name:'Open sidebar menu'}).click();
   await page.getByRole('link',{name:'Dashboard',exact:false}).click();
   assert(await page.locator('#page-dashboard').isVisible());
   await page.getByRole('button',{name:'WhatsApp Notifications'}).click();
   const panel=await page.locator('#wa-panel').boundingBox();
   assert(panel.x>=0 && panel.x+panel.width<=width,'settings fits screen horizontally');
   assert(panel.y>=0 && panel.y+panel.height<=height,'settings fits screen vertically');
   await page.getByRole('button',{name:'Open sidebar menu'}).click();
   assert(!(await page.locator('#wa-panel').isVisible()),'menu closes settings');
   await page.keyboard.press('Escape');
   assert.equal(await page.locator('#sidebar-toggle').getAttribute('aria-expanded'),'false');
  }else{
   assert(!(await page.locator('#sidebar-toggle').isVisible()));
   assert(await page.locator('#sidebar').isVisible());
  }
  await page.screenshot({path:'qa-screenshots/dashboard-'+width+'x'+height+'.png'});
  console.log('PASS portal layout and menu interactions '+width+'x'+height);
  await page.close();
 }
} finally {await browser.close();server.close();}
