import { chromium } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync, readFileSync } from 'node:fs';
import assert from 'node:assert/strict';
const adb = process.env.LOCALAPPDATA + '/Android/Sdk/platform-tools/adb.exe';
const out = 'storage/app/pwa-android-test';
mkdirSync(out, {recursive:true});
const b = await chromium.connectOverCDP('http://127.0.0.1:9222');
const p = b.contexts()[0].pages().find(p=>p.url().includes('localhost:8321'));
const base='http://localhost:8321';
const mode=process.argv[2] || 'offline';
const go=async(path)=>{await p.goto(base+path,{waitUntil:'domcontentloaded',timeout:30000});await p.waitForFunction(()=>window.SosPwa?.db?.isOpen());};
const click=async(selector)=>{await p.locator(selector).first().click({timeout:10000});};
const report={mode,started:new Date().toISOString(),documents:[]};
try {
 if(mode==='offline') {
  if(execFileSync(adb,['reverse','--list'],{encoding:'utf8'}).includes('tcp:8321')) execFileSync(adb,['reverse','--remove','tcp:8321']);
  execFileSync(adb,['shell','svc','wifi','disable']);
  execFileSync(adb,['shell','svc','data','disable']);
 } else {
  execFileSync(adb,['reverse','tcp:8321','tcp:8321']);
  execFileSync(adb,['shell','svc','wifi','enable']);
  execFileSync(adb,['shell','svc','data','enable']);
 }
 await go('/invoicing/offline/drafts');
 assert.equal(await p.evaluate(()=>window.SOS_USER_NAME),'Operador da Bancada');
 report.connectivity=await p.evaluate(async()=>({online:navigator.onLine,ping:await fetch('/api/v1/invoicing/ping',{signal:AbortSignal.timeout(4000)}).then(r=>r.status).catch(()=>null)}));
 if(mode==='offline') assert.equal(report.connectivity.ping,null,'Network must actually be disconnected');
 if(mode==='sync') {
  for(let i=0;i<3;i++){await p.evaluate(()=>window.SosPwa.sync(true));await p.waitForFunction(()=>!window.SosPwa.state.syncing,null,{timeout:90000});}
  const prior=JSON.parse(readFileSync(out+'/offline.json','utf8'));
  report.documents=await p.evaluate(async(ids)=>{const ds=await window.SosPwa.db.draft_documents.toArray();return ds.filter(d=>ids.includes(d.local_uuid));},prior.documents.map(d=>d.local_uuid));
  report.queue=await p.evaluate(()=>window.SosPwa.db.sync_queue.where('status').notEqual('done').toArray());
  for(const d of report.documents) assert.ok(d._server_id,JSON.stringify(d));
 } else {
  const name='Android '+mode+' '+Date.now();
  await go('/invoicing/offline/clients/new');
  await p.locator('input[x-model="form.name"]').fill(name);
  await p.locator('input[x-model="form.nif"]').fill('5'+String(Date.now()).slice(-9));
  await p.locator('input[x-model="form.phone"]').fill('923000123');
  await click('button[type="submit"]:has-text("Guardar")');
  await p.waitForURL('**/invoicing/offline/clients');
  report.client=await p.evaluate(async(n)=>(await window.SosPwa.db.clients.toArray()).find(c=>c.name===n),name);
  assert.ok(report.client,'Client created from visible form');
  for(const type of ['FR','FT','proforma']) for(const named of [false,true]) {
   await go('/invoicing/offline/drafts/new');
   const label={FR:'Fatura-Recibo',FT:'Fatura',proforma:'Proforma'}[type];
   await p.locator('button').filter({hasText:new RegExp('^\\s*'+label+'\\s*$')}).first().click({timeout:10000});
   if(named){await click('button:has-text("Selecionar cliente")');await p.locator('input[x-model="clientSearch"]').fill(name);await click('[x-show="showClientPicker"] button:has-text("'+name+'")');}
   await click('button:has-text("Adicionar")');
   await click('[x-show="showProductPicker"] button[type="button"]');
   await click('button:has-text("Emitir Documento")');
   await p.getByText(/Documento guardado/).first().waitFor();
   const doc=await p.evaluate(()=>window.SosPwa.db.draft_documents.orderBy('created_at').reverse().first());
   assert.equal(doc.doc_type,type);if(named)assert.equal(doc.client_name,name);assert.ok(doc.total>0);
   report.documents.push(doc);console.log(type,named?'client':'final',doc.local_uuid,doc.total);
  }
  await p.screenshot({path:out+'/'+mode+'.png'});
  if(mode==='offline') {
   report.backup=await p.evaluate(async()=>{
    const original=URL.createObjectURL;let saved;
    URL.createObjectURL=function(blob){saved=blob;return original.call(this,blob);};
    try{await window.SosPwa.exportarCopia();return await saved.text();}finally{URL.createObjectURL=original;}
   });
   writeFileSync(out+'/backup.json',report.backup);delete report.backup;
   await p.reload({waitUntil:'domcontentloaded'});
   await p.waitForFunction(()=>window.SosPwa?.db?.isOpen());
   const ids=await p.evaluate(()=>window.SosPwa.db.draft_documents.toCollection().primaryKeys());
   for(const d of report.documents)assert.ok(ids.includes(d.local_uuid));
  }
 }
 report.ok=true;
} catch(e){report.error=e.stack;console.error(e);process.exitCode=1;await p.screenshot({path:out+'/error.png'}).catch(()=>{});}
finally{writeFileSync(out+'/'+mode+'.json',JSON.stringify(report,null,2));await b.close();}
