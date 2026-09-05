import {chromium} from '@playwright/test';
import {readFileSync,writeFileSync} from 'node:fs';
const recovery=process.argv.includes('--recovery');
const b=recovery?await chromium.launch():await chromium.connectOverCDP('http://127.0.0.1:9222');
const context=recovery?await b.newContext():b.contexts()[0];
const p=await context.newPage();
const results=[];
try {
 if(recovery){
  await p.goto('http://localhost:8321/invoicing/offline/login');
  await p.locator('input[name=email]').fill('bancada@pwa.local');
  await p.locator('input[name=password]').fill('bancada-pwa-2026');
  await p.locator('button[type=submit]').click();
  await p.waitForURL(url => !url.pathname.endsWith('/login'), {waitUntil:'domcontentloaded'});
 }
 await p.goto('http://localhost:8321/invoicing/importar-copia-offline',{waitUntil:'domcontentloaded'});
 for(let i=0;i<2;i++) {
  await p.locator('input[type=file]').setInputFiles({name:'backup.json',mimeType:'application/json',buffer:readFileSync('storage/app/pwa-android-test/backup.json')});
  await p.locator('button[wire\\:click="importar"]').click({timeout:30000});
  await p.locator('h3').filter({hasText:'Resultado'}).waitFor({timeout:20000});
  const result=await p.evaluate(()=>{const root=document.querySelector('input[type=file]').closest('[wire\\:id]');return window.Livewire.find(root.getAttribute('wire:id')).get('resultado');});
  results.push(result);console.log(JSON.stringify(result));
  await p.screenshot({path:'storage/app/pwa-android-test/import-'+i+'.png'});
  await p.reload();
 }
}catch(e){results.push({error:e.stack});console.error(e);process.exitCode=1;}
finally{writeFileSync('storage/app/pwa-android-test/import.json',JSON.stringify(results,null,2));await b.close();}
